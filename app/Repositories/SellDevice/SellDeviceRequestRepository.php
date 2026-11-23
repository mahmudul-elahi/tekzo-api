<?php

namespace App\Repositories\SellDevice;

use App\Models\SellDeviceRequest\SellDeviceRequest;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class SellDeviceRequestRepository extends BaseRepository
{
    public const RELATIONS = ['category', 'brand', 'newDistrict', 'images', 'features'];

    public function __construct(SellDeviceRequest $sellDeviceRequest)
    {
        $this->model = $sellDeviceRequest;
    }

    /**
     * A customer's requests, newest first.
     */
    public function paginateForContact(mixed $contactId, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with(self::RELATIONS)
            ->where('contact_id', $contactId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SellDeviceRequest
    {
        return $this->query()->create($attributes);
    }

    public function hasPrimaryImage(SellDeviceRequest $request): bool
    {
        return $request->images()->where('is_primary', true)->exists();
    }

    public function addImage(SellDeviceRequest $request, string $path, bool $isPrimary): void
    {
        $request->images()->create([
            'image' => $path,
            'is_primary' => $isPrimary,
        ]);
    }

    public function markOldestImagePrimary(SellDeviceRequest $request): void
    {
        $request->images()->oldest('id')->first()?->update(['is_primary' => true]);
    }

    /**
     * @param  array<int, int>  $imageIds
     * @return Collection<int, Model>
     */
    public function imagesByIds(SellDeviceRequest $request, array $imageIds): Collection
    {
        return $request->images()->whereIn('id', $imageIds)->get();
    }

    public function addFeature(SellDeviceRequest $request, string $name, string $value): void
    {
        $request->features()->create([
            'feature_name' => $name,
            'feature_value' => $value,
        ]);
    }

    public function deleteFeatures(SellDeviceRequest $request): void
    {
        $request->features()->delete();
    }
}
