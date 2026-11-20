<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ClearStaleChunkUploads extends Command
{
    protected $signature = 'app:clear-stale-chunk-uploads';
    protected $description = 'Delete abandoned chunk upload folders (client never sent the last chunk)';

    private const CHUNK_DISK = 'local';
    private const CHUNK_FOLDER = 'chunk-uploads';
    private const MAX_AGE_SECONDS = 3 * 60 * 60;

    public function handle(): void
    {
        $disk = Storage::disk(self::CHUNK_DISK);

        foreach ($disk->directories(self::CHUNK_FOLDER) as $directory) {
            $lastModified = collect($disk->files($directory))
                ->map(fn($file) => $disk->lastModified($file))
                ->max();

            if ($lastModified !== null && (time() - $lastModified) > self::MAX_AGE_SECONDS) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
