<?php

namespace App\Services\SellDevice;

use App\Exceptions\GeneralException;
use App\Models\SellDeviceRequest\SellDeviceRequest;
use App\Repositories\SellDevice\SellDeviceRequestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SellDeviceRequestService
{
    public const IMAGE_FOLDER = 'sell-device-requests';

    private const NON_COLUMN_FIELDS = ['images', 'remove_image_ids', 'features'];

    public function __construct(private SellDeviceRequestRepository $requests) {}

    public function paginateForContact(mixed $contactId, int $perPage): LengthAwarePaginator
    {
        return $this->requests->paginateForContact($contactId, $perPage);
    }

    /**
     * @throws GeneralException when the request belongs to someone else
     */
    public function showForContact(SellDeviceRequest $request, mixed $contactId): SellDeviceRequest
    {
        $this->authorizeOwner($request, $contactId);

        return $request->load(SellDeviceRequestRepository::RELATIONS);
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws Throwable
     */
    public function create(array $validated, mixed $contactId): SellDeviceRequest
    {
        $sellDeviceRequest = DB::transaction(function () use ($validated, $contactId) {
            $sellDeviceRequest = $this->requests->create([
                ...collect($validated)->except(self::NON_COLUMN_FIELDS)->toArray(),
                'contact_id' => $contactId,
            ]);

            $this->syncImages($sellDeviceRequest, $validated['images'] ?? null);
            $this->syncFeatures($sellDeviceRequest, $validated['features'] ?? null);

            return $sellDeviceRequest;
        });

        return $sellDeviceRequest->load(SellDeviceRequestRepository::RELATIONS);
    }

    /**
     * Updates the request; removes the listed images, adds new ones, and replaces the
     * features only when a "features" key was sent.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws Throwable
     */
    public function update(SellDeviceRequest $sellDeviceRequest, array $validated, mixed $contactId): SellDeviceRequest
    {
        $this->authorizeOwner($sellDeviceRequest, $contactId);

        DB::transaction(function () use ($validated, $sellDeviceRequest) {
            $sellDeviceRequest->update(
                collect($validated)->except(self::NON_COLUMN_FIELDS)->toArray()
            );

            if (! empty($validated['remove_image_ids'])) {
                $this->removeImages($sellDeviceRequest, $validated['remove_image_ids']);
            }

            $this->syncImages($sellDeviceRequest, $validated['images'] ?? null);

            if (array_key_exists('features', $validated)) {
                $this->requests->deleteFeatures($sellDeviceRequest);
                $this->syncFeatures($sellDeviceRequest, $validated['features'] ?? null);
            }
        });

        return $sellDeviceRequest->load(SellDeviceRequestRepository::RELATIONS);
    }

    /**
     * @throws GeneralException when the request belongs to someone else
     */
    public function delete(SellDeviceRequest $sellDeviceRequest, mixed $contactId): void
    {
        $this->authorizeOwner($sellDeviceRequest, $contactId);

        $sellDeviceRequest->delete();
    }

    /**
     * Attach uploaded images (paths returned by the upload endpoint). Only the first image
     * marked primary becomes primary; when none is, the oldest image is made primary.
     *
     * @param  array<int, array{path: string, is_primary?: bool}>|null  $images
     *
     * @throws GeneralException when a path is outside the upload folder or no longer exists
     */
    private function syncImages(SellDeviceRequest $sellDeviceRequest, ?array $images): void
    {
        if (empty($images)) {
            return;
        }

        $disk = Storage::disk(config('filesystems.default'));
        $hasExistingPrimary = $this->requests->hasPrimaryImage($sellDeviceRequest);

        foreach ($images as $image) {
            $path = $image['path'];

            if (! str_starts_with($path, self::IMAGE_FOLDER.'/') || ! $disk->exists($path)) {
                throw new GeneralException('Invalid or expired image, please re-upload it', 422);
            }

            $isPrimary = ! $hasExistingPrimary && ! empty($image['is_primary']);

            $this->requests->addImage($sellDeviceRequest, $path, $isPrimary);

            $hasExistingPrimary = $hasExistingPrimary || $isPrimary;
        }

        if (! $hasExistingPrimary) {
            $this->requests->markOldestImagePrimary($sellDeviceRequest);
        }
    }

    /**
     * @param  array<int, int>  $imageIds
     */
    private function removeImages(SellDeviceRequest $sellDeviceRequest, array $imageIds): void
    {
        foreach ($this->requests->imagesByIds($sellDeviceRequest, $imageIds) as $image) {
            Storage::disk(config('filesystems.default'))->delete($image->image);
            $image->delete();
        }
    }

    /**
     * @param  array<int, array{feature_name: string, feature_value: string}>|null  $features
     */
    private function syncFeatures(SellDeviceRequest $sellDeviceRequest, ?array $features): void
    {
        if (empty($features)) {
            return;
        }

        foreach ($features as $feature) {
            $this->requests->addFeature($sellDeviceRequest, $feature['feature_name'], $feature['feature_value']);
        }
    }

    /**
     * @throws GeneralException
     */
    private function authorizeOwner(SellDeviceRequest $sellDeviceRequest, mixed $contactId): void
    {
        if ($sellDeviceRequest->contact_id != $contactId) {
            throw new GeneralException('Sell device request not found', 404);
        }
    }
}
