<?php

namespace App\Services\SellDevice;

use App\Exceptions\GeneralException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Chunked photo uploads. The client splits a file into fixed-size chunks (grouped by
 * `identifier`) and posts them one by one, so a slow connection can retry a single chunk.
 * When the last chunk arrives the chunks are merged, validated, and moved to permanent storage.
 */
class SellDeviceImageUploadService
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    private const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024;

    private const CHUNK_DISK = 'local';

    private const CHUNK_FOLDER = 'chunk-uploads';

    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    /**
     * Store one chunk. Returns ['done' => percent] while chunks are missing,
     * or ['path' => stored path] once the image is complete.
     *
     * @return array{done: int}|array{path: string}
     *
     * @throws GeneralException when the merged file is incomplete, too large, or not an image
     */
    public function receiveChunk(mixed $customerId, string $identifier, string $filename, int $chunkIndex, int $totalChunks, UploadedFile $chunk): array
    {
        $chunkDisk = Storage::disk(self::CHUNK_DISK);
        $chunkDir = self::CHUNK_FOLDER.'/'.$customerId.'/'.$identifier;

        $chunk->storeAs($chunkDir, "chunk_{$chunkIndex}", self::CHUNK_DISK);

        $receivedChunks = count($chunkDisk->files($chunkDir));

        if ($receivedChunks < $totalChunks) {
            return [
                'done' => (int) round(($receivedChunks / $totalChunks) * 100),
            ];
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            $chunkDisk->deleteDirectory($chunkDir);
            throw new GeneralException('Only jpg, jpeg, png and webp images are allowed', 422);
        }

        $mergedPath = $this->mergeChunks($chunkDisk, $chunkDir, $totalChunks);

        if (filesize($mergedPath) > self::MAX_IMAGE_SIZE_BYTES) {
            unlink($mergedPath);
            $chunkDisk->deleteDirectory($chunkDir);
            throw new GeneralException('Image size must not exceed 10MB', 422);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $mergedPath);
        finfo_close($finfo);

        if (! isset(self::MIME_TO_EXTENSION[$detectedMime])) {
            unlink($mergedPath);
            $chunkDisk->deleteDirectory($chunkDir);
            throw new GeneralException('Only jpg, jpeg, png and webp images are allowed', 422);
        }

        $extension = self::MIME_TO_EXTENSION[$detectedMime];

        // No visibility flag here — the bucket has ACLs disabled (bucket
        // owner enforced), so requesting 'public' ACL makes S3 reject the
        // write with AccessControlListNotSupported. Public access to these
        // objects is handled by the bucket/CloudFront policy instead.
        $path = Storage::disk(config('filesystems.default'))
            ->putFileAs(SellDeviceRequestService::IMAGE_FOLDER, new File($mergedPath), Str::uuid().'.'.$extension);

        unlink($mergedPath);
        $chunkDisk->deleteDirectory($chunkDir);

        return ['path' => $path];
    }

    /**
     * @throws GeneralException
     */
    private function mergeChunks(FilesystemAdapter $chunkDisk, string $chunkDir, int $totalChunks): string
    {
        $mergedPath = $chunkDisk->path($chunkDir.'/merged');
        $out = fopen($mergedPath, 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $chunkDisk->path("{$chunkDir}/chunk_{$i}");

            if (! file_exists($chunkPath)) {
                fclose($out);
                @unlink($mergedPath);
                $chunkDisk->deleteDirectory($chunkDir);
                throw new GeneralException('Upload incomplete, please retry', 422);
            }

            $in = fopen($chunkPath, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);

        return $mergedPath;
    }
}
