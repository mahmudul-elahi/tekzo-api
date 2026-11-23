<?php

namespace App\Repositories\Brand;

use App\Enums\StatusEnum;
use App\Models\Brand\Brand;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class BrandRepository extends BaseRepository
{
    public function __construct(Brand $brand)
    {
        $this->model = $brand;
    }

    /**
     * Active brands in display order.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, Brand>
     */
    public function activeOrdered(array $columns): Collection
    {
        return $this->query()
            ->where('status', true)
            ->orderBy('order', 'asc')
            ->select($columns)
            ->get();
    }

    /**
     * Name and slug of the active brands among the given ids, by name.
     *
     * @param  SupportCollection<int, int>|array<int, int>  $brandIds
     * @return Collection<int, Brand>
     */
    public function activeNamesByIds(SupportCollection|array $brandIds): Collection
    {
        return $this->query()
            ->whereIn('id', $brandIds)
            ->where('status', true)
            ->select(['name', 'slug'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Brands with at least one unsold pre-owned unit, by name.
     *
     * @return Collection<int, Brand>
     */
    public function withAvailablePreOwned(): Collection
    {
        return $this->query()
            ->select(['id', 'name', 'slug'])
            ->whereHas('products.preOwned', fn ($q) => $q->where('is_sold', '!=', true))
            ->orderBy('name')
            ->get();
    }

    public function findWithDescriptionBySlug(string $slug): ?Brand
    {
        return $this->query()
            ->whereSlug($slug)
            ->with(['longDescription:id,model_id,description'])
            ->first();
    }

    /**
     * Active brands among the given ids, with the fields a filter list needs.
     *
     * @param  SupportCollection<int, int>|array<int, int>  $brandIds
     * @return Collection<int, Brand>
     */
    public function activeForFilter(SupportCollection|array $brandIds): Collection
    {
        return $this->query()
            ->whereIn('id', $brandIds)
            ->where('status', StatusEnum::Active->value)
            ->select(['id', 'name', 'slug', 'primary_logo'])
            ->get();
    }
}
