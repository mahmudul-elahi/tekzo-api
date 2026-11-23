<?php

namespace App\Services\Product;

use App\Models\Brand\Brand;
use App\Models\Category\Category;
use App\Repositories\Brand\BrandRepository;
use App\Repositories\Category\CategoryRepository;
use App\Repositories\Product\ProductRepository;

class ProductListingService
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private BrandRepository $brands,
    ) {}

    public function findCategoryBanner(string $slug): ?Category
    {
        return $this->categories->findBannerBySlug($slug);
    }

    /**
     * A category with a page of its (and its descendants') products set as the "products" relation,
     * and every brand present in the filtered results as "available_brands".
     *
     * @param  array{brand: ?string, stock: mixed, min_price: mixed, max_price: mixed, sort_by: ?string, per_page: int}  $options
     */
    public function categoryWithProducts(string $slug, array $options): ?Category
    {
        $category = $this->categories->findWithChildrenBySlug($slug);

        if (! $category) {
            return null;
        }

        $baseQuery = $this->products->visibleInCategoriesQuery(
            $category->getAllDescendantIds(),
            $options['brand'],
            $this->filters($options),
        );

        // The brand filter list must reflect every matching product, not just the current page.
        $brandIds = (clone $baseQuery)->pluck('brand_id')->filter()->unique()->values();
        $brands = $this->brands->activeForFilter($brandIds);

        $products = $this->products->paginateCategoryListing($baseQuery, $options['sort_by'], $options['per_page']);

        $category->setRelation('products', $products);
        $category->setAttribute('available_brands', $brands);

        return $category;
    }

    /**
     * A brand with a page of its visible products set as the "products" relation.
     *
     * @param  array{category: ?string, stock: mixed, min_price: mixed, max_price: mixed, sort_by: ?string, per_page: int}  $options
     */
    public function brandWithProducts(string $slug, array $options): ?Brand
    {
        $brand = $this->brands->findWithDescriptionBySlug($slug);

        if (! $brand) {
            return null;
        }

        $products = $this->products->paginateBrandListing(
            $brand->id,
            $this->filters($options),
            $options['category'],
            $options['sort_by'],
            $options['per_page'],
        );

        $brand->setRelation('products', $products);

        return $brand;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{stock: mixed, min_price: mixed, max_price: mixed}
     */
    private function filters(array $options): array
    {
        return [
            'stock' => $options['stock'],
            'min_price' => $options['min_price'],
            'max_price' => $options['max_price'],
        ];
    }
}
