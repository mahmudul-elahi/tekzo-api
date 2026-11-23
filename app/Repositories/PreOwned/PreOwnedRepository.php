<?php

namespace App\Repositories\PreOwned;

use App\Models\PreOwned\PreOwned;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class PreOwnedRepository extends BaseRepository
{
    public function __construct(PreOwned $preOwned)
    {
        $this->model = $preOwned;
    }

    public function existsById(mixed $id): bool
    {
        return $this->query()
            ->where('id', $id)
            ->exists();
    }

    /**
     * A pre-owned item with everything the product detail page needs.
     */
    public function findForDetail(mixed $id): ?PreOwned
    {
        return $this->query()
            ->where('id', $id)
            ->with([
                'images',
                'cares.care',
                'gift.variants.product',
                'attributes' => function ($q) {
                    $q->with(['attribute', 'attributeValue']);
                },
                'product' => function ($q) {
                    $q->with([
                        'category:id,parent_id,name,slug',
                        'brand:id,name,slug,primary_logo,secondary_logo,shop_banner',
                        'specificationItems.parent',
                        'specificationItems.sub',
                        'preOwnedSeo',
                    ])->withCount('productViews as view_count')
                        ->withCount('reviews');
                },
            ])
            ->first();
    }

    /**
     * Random unsold pre-owned items of the same product, skipping the current one.
     *
     * @return Collection<int, PreOwned>
     */
    public function randomUnsoldOfProduct(string $productSlug, mixed $exceptId, int $limit): Collection
    {
        return $this->query()
            ->where('is_sold', '!=', true)
            ->whereHas('product', function ($q) use ($productSlug) {
                $q->where('slug', $productSlug);
            })
            ->with($this->relatedRelations())
            ->where('id', '!=', $exceptId)
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    /**
     * Random pre-owned items, skipping the given ids.
     *
     * @param  array<int, mixed>  $excludeIds
     * @return Collection<int, PreOwned>
     */
    public function randomExcept(array $excludeIds, int $limit): Collection
    {
        return $this->query()
            ->with($this->relatedRelations())
            ->whereNotIn('id', $excludeIds)
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    /**
     * The 10 newest unsold pre-owned items.
     *
     * @return Collection<int, PreOwned>
     */
    public function latestUnsold(): Collection
    {
        return $this->query()
            ->with(['product' => function ($q) {
                $q->select(['id', 'product_name', 'slug', 'brand_id', 'category_id'])
                    ->with(['brand:id,name,slug', 'category:id,name,slug']);
            },   'attributes' => function ($q) {
                $q->with('attribute', 'attributeValue');
            }])
            ->where('is_sold', '!=', true)
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    /**
     * @return array<string, \Closure>
     */
    private function relatedRelations(): array
    {
        return [
            'product' => function ($q) {
                $q->select(['id', 'product_name', 'headline', 'slug', 'brand_id', 'category_id', 'short_description']);
            },
            'attributes' => function ($q) {
                $q->with('attribute', 'attributeValue');
            },
        ];
    }
}
