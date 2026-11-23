<?php

namespace App\Services\PreOwned;

use App\Models\Brand\Brand;
use App\Models\PreOwned\PreOwned;
use App\Repositories\Brand\BrandRepository;
use App\Repositories\PreOwned\PreOwnedRepository;
use App\Repositories\Product\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class PreOwnedService
{
    public function __construct(
        private ProductRepository $products,
        private PreOwnedRepository $preOwneds,
        private BrandRepository $brands,
    ) {}

    /**
     * @param  array{brand: ?string, category: ?string, search: ?string, stock: mixed, min_price: mixed, max_price: mixed, sort_by: mixed}  $filters
     */
    public function paginateModels(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->products->paginateWithPreOwnedUnits($filters, $perPage);
    }

    /**
     * A product's pre-owned units plus its first unit (for brand and SEO), or null when it has none.
     *
     * @param  array{condition: mixed, storage: mixed, stock: mixed, min_price: mixed, max_price: mixed, sort_by: mixed}  $filters
     * @return array{first: PreOwned, units: LengthAwarePaginator}|null
     */
    public function unitsOfProduct(string $productSlug, array $filters, int $perPage): ?array
    {
        $first = $this->preOwneds->findFirstOfProductWithBrand($productSlug);

        if (! $first) {
            return null;
        }

        return [
            'first' => $first,
            'units' => $this->preOwneds->paginateOfProduct($productSlug, $filters, $perPage),
        ];
    }

    /**
     * Brands that actually have at least one available (not sold) pre-owned unit, for the
     * marketplace filter sidebar. Narrower than the general brand endpoints on purpose.
     *
     * @return Collection<int, Brand>
     */
    public function brandsWithAvailableUnits(): Collection
    {
        return $this->brands->withAvailablePreOwned();
    }

    /**
     * One "shelf" per product model, each with up to $perGroupLimit of its available units.
     *
     * Units for every product on the page are fetched in a single query and limited per
     * shelf in memory, since a limited eager load only limits the whole query, not per parent.
     *
     * @param  array<int, string>  $brandSlugs
     * @return array{products: LengthAwarePaginator, unitsByProduct: SupportCollection<int, SupportCollection<int, PreOwned>>}
     */
    public function shelves(array $brandSlugs, int $perGroupLimit, int $perPage): array
    {
        $products = $this->products->paginateWithAvailablePreOwned($brandSlugs, $perPage);
        $unitsByProduct = $this->preOwneds->availableUnitsGroupedByProduct($products->pluck('id'))
            ->map(fn ($units) => $units->take($perGroupLimit));

        return [
            'products' => $products,
            'unitsByProduct' => $unitsByProduct,
        ];
    }

    /**
     * SEO for a product's pre-owned listing page, from its PreOwnedProductSeo entry when present.
     *
     * @return array<string, mixed>
     */
    public function productListingSeo(PreOwned $first): array
    {
        $seo = $this->preOwneds->findProductSeo($first->product_id);

        $base = [
            'title' => $first->product->product_name,
            'canonical' => config('services.front_base_url').'/used-device/'.$first->product->slug,
            'ogImage' => $first?->thumbnail_image ?? null,
            'siteName' => config('app.name'),
        ];

        if (! $seo) {
            return array_merge($base, [
                'description' => '',
                'tags' => '',
                'longDescription' => '',
            ]);
        }

        return array_merge($base, [
            'title' => $seo->title ?? $first->product->product_name,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'long_description' => $seo->long_description,
        ]);
    }
}
