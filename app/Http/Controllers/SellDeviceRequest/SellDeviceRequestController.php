<?php

namespace App\Http\Controllers\SellDeviceRequest;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SellDeviceRequest\SellDeviceRequestRequest;
use App\Http\Resources\SellDeviceRequest\SellDeviceRequestResource;
use App\Models\SellDeviceRequest\SellDeviceRequest as SellDeviceRequestModel;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SellDeviceRequestController extends Controller
{
    private const RELATIONS = ['category', 'brand', 'newDistrict', 'images', 'features'];

    private const IMAGE_FOLDER = 'sell-device-requests';

    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    private const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024;

    private const CHUNK_DISK = 'local';

    private const CHUNK_FOLDER = 'chunk-uploads';

    public function index()
    {
        // ->through() keeps this a paginator (data/current_page/last_page/...)
        // instead of Resource::collection(), which drops that structure down
        // to a flat array once it's re-serialized through successResponse().
        $requests = SellDeviceRequestModel::query()
            ->with(self::RELATIONS)
            ->where('contact_id', auth()->guard('customer')->id())
            ->orderByDesc('id')
            ->paginate(clampPerPage(request('per_page'), 10, 48))
            ->through(fn ($r) => SellDeviceRequestResource::make($r)->resolve());

        return successResponse($requests);
    }

    public function show(SellDeviceRequestModel $sell_device_request)
    {
        $this->authorizeOwner($sell_device_request);

        return successResponse(
            SellDeviceRequestResource::make($sell_device_request->load(self::RELATIONS))
        );
    }

    /**
     * Receives one chunk of a large photo. The client splits the raw file into fixed-size
     * chunks (identified by `identifier`) and posts them one by one so a slow/unstable
     * connection can retry a single chunk instead of resending the whole file. Once the
     * last chunk lands, the chunks are merged in order into one file, validated, moved to
     * permanent storage, and its path is returned for use in the `images` array on store/update.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048'],
            'identifier' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'filename' => ['required', 'string'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $customerId = auth()->guard('customer')->id();
        $identifier = $request->input('identifier');
        $chunkIndex = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');

        $chunkDisk = Storage::disk(self::CHUNK_DISK);
        $chunkDir = self::CHUNK_FOLDER.'/'.$customerId.'/'.$identifier;

        $request->file('file')->storeAs($chunkDir, "chunk_{$chunkIndex}", self::CHUNK_DISK);

        $receivedChunks = count($chunkDisk->files($chunkDir));

        if ($receivedChunks < $totalChunks) {
            return successResponse([
                'done' => (int) round(($receivedChunks / $totalChunks) * 100),
            ]);
        }

        $extension = strtolower(pathinfo($request->input('filename'), PATHINFO_EXTENSION));

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

        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
        ];

        if (! isset($mimeToExt[$detectedMime])) {
            unlink($mergedPath);
            $chunkDisk->deleteDirectory($chunkDir);
            throw new GeneralException('Only jpg, jpeg, png and webp images are allowed', 422);
        }

        $extension = $mimeToExt[$detectedMime];

        // No visibility flag here — the bucket has ACLs disabled (bucket
        // owner enforced), so requesting 'public' ACL makes S3 reject the
        // write with AccessControlListNotSupported. Public access to these
        // objects is handled by the bucket/CloudFront policy instead.
        $path = Storage::disk(config('filesystems.default'))
            ->putFileAs(self::IMAGE_FOLDER, new File($mergedPath), Str::uuid().'.'.$extension);

        unlink($mergedPath);
        $chunkDisk->deleteDirectory($chunkDir);

        return successResponse(['path' => $path]);
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

    /**
     * @throws Throwable
     */
    public function store(SellDeviceRequestRequest $request)
    {
        $validated = $request->validated();

        $sellDeviceRequest = DB::transaction(function () use ($validated) {
            $sellDeviceRequest = SellDeviceRequestModel::query()->create([
                ...collect($validated)->except(['images', 'remove_image_ids', 'features'])->toArray(),
                'contact_id' => auth()->guard('customer')->id(),
            ]);

            $this->syncImages($sellDeviceRequest, $validated['images'] ?? null);
            $this->syncFeatures($sellDeviceRequest, $validated['features'] ?? null);

            return $sellDeviceRequest;
        });

        return successResponse(
            SellDeviceRequestResource::make($sellDeviceRequest->load(self::RELATIONS)),
            201
        );
    }

    /**
     * @throws Throwable
     */
    public function update(SellDeviceRequestRequest $request, SellDeviceRequestModel $sell_device_request)
    {
        $this->authorizeOwner($sell_device_request);

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $sell_device_request) {
            $sell_device_request->update(
                collect($validated)->except(['images', 'remove_image_ids', 'features'])->toArray()
            );

            if (! empty($validated['remove_image_ids'])) {
                $this->removeImages($sell_device_request, $validated['remove_image_ids']);
            }

            $this->syncImages($sell_device_request, $validated['images'] ?? null);

            if (array_key_exists('features', $validated)) {
                $sell_device_request->features()->delete();
                $this->syncFeatures($sell_device_request, $validated['features'] ?? null);
            }
        });

        return successResponse(
            SellDeviceRequestResource::make($sell_device_request->load(self::RELATIONS))
        );
    }

    public function destroy(SellDeviceRequestModel $sell_device_request)
    {
        $this->authorizeOwner($sell_device_request);

        $sell_device_request->delete();

        return responseMessage('Sell device request deleted successfully');
    }

    /**
     * @throws GeneralException
     */
    private function syncImages(SellDeviceRequestModel $sellDeviceRequest, ?array $images): void
    {
        if (empty($images)) {
            return;
        }

        $disk = Storage::disk(config('filesystems.default'));
        $hasExistingPrimary = $sellDeviceRequest->images()->where('is_primary', true)->exists();

        foreach ($images as $image) {
            $path = $image['path'];

            if (! str_starts_with($path, self::IMAGE_FOLDER.'/') || ! $disk->exists($path)) {
                throw new GeneralException('Invalid or expired image, please re-upload it', 422);
            }

            $isPrimary = ! $hasExistingPrimary && ! empty($image['is_primary']);

            $sellDeviceRequest->images()->create([
                'image' => $path,
                'is_primary' => $isPrimary,
            ]);

            $hasExistingPrimary = $hasExistingPrimary || $isPrimary;
        }

        if (! $hasExistingPrimary) {
            $sellDeviceRequest->images()->oldest('id')->first()?->update(['is_primary' => true]);
        }
    }

    private function removeImages(SellDeviceRequestModel $sellDeviceRequest, array $imageIds): void
    {
        $images = $sellDeviceRequest->images()->whereIn('id', $imageIds)->get();

        foreach ($images as $image) {
            Storage::disk(config('filesystems.default'))->delete($image->image);
            $image->delete();
        }
    }

    private function syncFeatures(SellDeviceRequestModel $sellDeviceRequest, ?array $features): void
    {
        if (empty($features)) {
            return;
        }

        foreach ($features as $feature) {
            $sellDeviceRequest->features()->create([
                'feature_name' => $feature['feature_name'],
                'feature_value' => $feature['feature_value'],
            ]);
        }
    }

    /**
     * @throws GeneralException
     */
    private function authorizeOwner(SellDeviceRequestModel $sellDeviceRequest): void
    {
        if ($sellDeviceRequest->contact_id != auth()->guard('customer')->id()) {
            throw new GeneralException('Sell device request not found', 404);
        }
    }
}
