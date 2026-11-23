<?php

namespace App\Services\Shop;

use App\Models\Brand\Brand;
use App\Models\Product\Product;
use App\Repositories\Brand\BrandRepository;
use App\Repositories\Product\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ShopService
{
    public function __construct(
        private ProductRepository $products,
        private BrandRepository $brands,
    ) {}

    /**
     * @param  array{brand: mixed, category: mixed, stock: mixed, min_price: mixed, max_price: mixed, sort_by: mixed}  $filters
     */
    public function paginateShop(array $filters, int $perPage, mixed $page): LengthAwarePaginator
    {
        return $this->products->paginateShop($filters, $perPage, $page);
    }

    /**
     * @return Collection<int, Product>
     */
    public function sitemapEntries(): Collection
    {
        return $this->products->sitemapEntries();
    }

    /**
     * @param  array{brand: mixed, stock: mixed, min_price: mixed, max_price: mixed, sort_by: mixed}  $filters
     */
    public function paginateRefurbished(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->products->paginateRefurbished($filters, $perPage);
    }

    /**
     * Active brands that have active refurbished products, by name.
     *
     * @return Collection<int, Brand>
     */
    public function refurbishedBrands(): Collection
    {
        return $this->brands->activeNamesByIds($this->products->refurbishedBrandIds());
    }
}
