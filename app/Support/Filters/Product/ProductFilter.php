<?php

namespace App\Support\Filters\Product;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductFilter
{
    public function __construct(protected array $filters) {}

    public function apply(Builder $query): Builder
    {
        $this->applyBrandFilter($query);
        $this->applyStockFilter($query);
        $this->applyPriceRangeFilter($query);
        $this->applyPriceSortFilter($query);
        $this->applyCategoryFilter($query);

        return $query;
    }

    //  BRAND FILTER
    private function applyBrandFilter(Builder $query): void
    {
        $brand = $this->filters['brand'] ?? null;

        if ($brand && $brand !== 'all') {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $brand));
        }
    }

    private function applyCategoryFilter(Builder $query): void
    {
        $category = $this->filters['category'] ?? null;

        if ($category && $category !== 'all') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }
    }

    // STOCK FILTER
    private function applyStockFilter(Builder $query): void
    {
        $stockStatus = $this->filters['stock'] ?? null;

        if (! $stockStatus || $stockStatus === 'all') {
            return;
        }

        // Soft-deleted variants aren't excluded automatically here (same gotcha
        // applyPriceRangeFilter/applyPriceSortFilter below already work around) —
        // without this, a product whose only "stock=true" row is soft-deleted
        // incorrectly counts as in-stock even though nothing sellable is left.
        $activeVariant = fn ($q) => $q->where('status', '1')->whereNull('deleted_at');

        if ($stockStatus === 'in_stock') {
            $query->whereHas('variants', fn ($q) => $activeVariant($q)->where('stock', true));
        } elseif ($stockStatus === 'out_of_stock') {
            $query->where(function ($q) use ($activeVariant) {
                $q->whereDoesntHave('variants', $activeVariant)
                    ->orWhere(function ($q2) use ($activeVariant) {
                        $q2->whereHas('variants', $activeVariant)
                            ->whereDoesntHave('variants', fn ($q3) => $activeVariant($q3)->where('stock', true));
                    });
            });
        }
    }

    // MIN / MAX PRICE FILTER
    private function applyPriceRangeFilter(Builder $query): void
    {
        $minPrice = $this->filters['min_price'] ?? null;
        $maxPrice = $this->filters['max_price'] ?? null;

        if ($minPrice === null && $maxPrice === null) {
            return;
        }

        $query->whereHas('variants.price', function ($q) use ($minPrice, $maxPrice) {
            if ($minPrice !== null) {
                $q->where('max_retail_price', '>=', (float) $minPrice);
            }
            if ($maxPrice !== null) {
                $q->where('max_retail_price', '<=', (float) $maxPrice);
            }
        });
    }

    //  PRICE SORT FILTER
    private function applyPriceSortFilter(Builder $query): void
    {
        $sortBy = $this->filters['sort_by'] ?? null;

        if (! in_array($sortBy, ['price_asc', 'price_desc'])) {
            return;
        }

        $direction = $sortBy === 'price_asc' ? 'asc' : 'desc';

        $query->addSelect([
            // The lowest final (post-discount) price, not the raw max_retail_price —
            // otherwise a product with a small discount can outrank a cheaper product
            // that has a bigger discount, since the sort wouldn't match what's displayed.
            'min_price' => DB::table('variant_prices')
                ->join('product_variants', 'product_variants.id', '=', 'variant_prices.product_variant_id')
                ->whereColumn('product_variants.product_id', 'products.id')
                ->whereNull('product_variants.deleted_at')
                ->where('product_variants.status', '1')
                ->where('variant_prices.max_retail_price', '>', 0)
                ->selectRaw('MIN(
                    CASE
                        WHEN variant_prices.discount_percent > 0 THEN variant_prices.max_retail_price - (variant_prices.max_retail_price * variant_prices.discount_percent / 100)
                        WHEN variant_prices.discount_fixed > 0   THEN variant_prices.max_retail_price - variant_prices.discount_fixed
                        ELSE variant_prices.max_retail_price
                    END
                )'),
        ])
            ->orderByRaw('min_price IS NULL ASC')
            ->orderBy('min_price', $direction);
    }
}
