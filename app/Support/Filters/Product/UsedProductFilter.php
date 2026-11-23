<?php

namespace App\Support\Filters\Product;

class UsedProductFilter
{
    public function __construct(protected array $filters) {}

    public function apply($query)
    {
        $this->applyConditionFilter($query);
        $this->applyStorageFilter($query);
        $this->applyStockFilter($query);
        $this->applyPriceFilter($query);
        $this->applySort($query);

        return $query;
    }

    private function applyConditionFilter($query)
    {
        $condition = $this->filters['condition'] ?? null;

        if ($condition && $condition !== 'all') {
            $condition = strtolower($condition); // normalize input

            $query->whereHas('attributes', function ($q) use ($condition) {
                $q->where('attribute_id', 4)
                    ->whereHas('attributeValue', function ($q2) use ($condition) {
                        $q2->whereRaw('LOWER(value) = ?', [$condition]);
                    });
            });
        }
    }

    private function applyStorageFilter($query)
    {
        $storage = $this->filters['storage'] ?? null;

        if ($storage && $storage !== 'all') {
            $query->whereHas('attributes', function ($q) use ($storage) {
                $q->where('attribute_id', 3)
                    ->whereHas('attributeValue', function ($q2) use ($storage) {
                        $q2->whereRaw('LOWER(value) = ?', [$storage]);
                    });
            });
        }
    }

    private function applyStockFilter($query)
    {
        $stock = $this->filters['stock'] ?? null;

        if ($stock === 'in_stock') {
            $query->where('is_sold', false);
        } elseif ($stock === 'out_of_stock') {
            $query->where('is_sold', true);
        }
    }

    private function applyPriceFilter($query)
    {
        $minPrice = $this->filters['min_price'] ?? null;
        $maxPrice = $this->filters['max_price'] ?? null;

        if ($minPrice !== null && $minPrice !== '') {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice !== null && $maxPrice !== '') {
            $query->where('price', '<=', $maxPrice);
        }
    }

    private function applySort($query)
    {
        // Every branch ends with `id` as a final tiebreaker: without a unique column to
        // fall back on, rows tied on price/created_at/is_sold have no guaranteed order
        // between separate paginated requests, which can duplicate or skip a row across
        // pages (MySQL is free to return ties in a different order each time).
        match ($this->filters['sort_by'] ?? null) {
            'price_asc' => $query->orderBy('price', 'asc')->orderBy('id', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc')->orderBy('id', 'asc'),
            'newest' => $query->orderByDesc('created_at')->orderBy('id', 'asc'),
            default => $query->orderByRaw('CASE WHEN is_sold = 1 THEN 1 ELSE 0 END ASC')->orderBy('id', 'asc'),
        };
    }
}
