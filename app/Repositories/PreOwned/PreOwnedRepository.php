<?php

namespace App\Repositories\PreOwned;

use App\Models\PreOwned\PreOwned;
use App\Models\PreOwned\PreOwnedProductSeo;
use App\Repositories\BaseRepository;
use App\Support\Filters\Product\UsedProductFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

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
     * An unsold unit of the given variant, row-locked for the current transaction.
     */
    public function findUnsoldForUpdate(mixed $id, mixed $variantId): ?PreOwned
    {
        return $this->query()
            ->where('id', $id)
            ->where('product_variant_id', $variantId)
            ->where('is_sold', false)
            ->lockForUpdate()
            ->first();
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
     * The first pre-owned unit of a product, with the product's brand banner.
     */
    public function findFirstOfProductWithBrand(string $productSlug): ?PreOwned
    {
        return $this->query()
            ->whereHas('product', function ($q) use ($productSlug) {
                $q->where('slug', $productSlug);
            })
            ->with(['product' => function ($q) {
                $q->select(['id', 'product_name', 'slug', 'brand_id'])
                    ->with(['brand:id,name,shop_banner']);
            }])
            ->first();
    }

    /**
     * A product's pre-owned units with condition/storage/stock/price filters and sorting applied.
     *
     * @param  array{condition: mixed, storage: mixed, stock: mixed, min_price: mixed, max_price: mixed, sort_by: mixed}  $filters
     */
    public function paginateOfProduct(string $productSlug, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->query()
            ->whereHas('product', function ($q) use ($productSlug) {
                $q->where('slug', $productSlug);
            });

        (new UsedProductFilter($filters))->apply($query);

        return $query
            ->with([
                'product' => function ($q) {
                    $q->select(['id', 'product_name', 'headline', 'slug', 'brand_id', 'category_id', 'short_description'])
                        ->with([
                            'specificationItems.parent',
                            'specificationItems.sub',
                        ]);
                },
                'images',
                'attributes' => function ($q) {
                    $q->with('attribute', 'attributeValue');
                },
            ])
            ->paginate($perPage);
    }

    /**
     * Unsold units of the given products, newest first, grouped by product id.
     *
     * @param  SupportCollection<int, int>  $productIds
     * @return SupportCollection<int, Collection<int, PreOwned>>
     */
    public function availableUnitsGroupedByProduct(SupportCollection $productIds): SupportCollection
    {
        return $this->query()
            ->whereIn('product_id', $productIds)
            ->where('is_sold', '!=', true)
            ->with([
                'attributes.attribute',
                'attributes.attributeValue',
                'product:id,product_name,headline,slug',
            ])
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id');
    }

    /**
     * The SEO entry for a product's pre-owned listing page.
     */
    public function findProductSeo(int $productId): ?PreOwnedProductSeo
    {
        return PreOwnedProductSeo::query()
            ->where('product_id', $productId)
            ->first();
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
