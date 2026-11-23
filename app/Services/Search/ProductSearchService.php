<?php

namespace App\Services\Search;

use App\Models\Product\Product;
use App\Repositories\Product\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductSearchService
{
    private const MIN_QUERY_LENGTH = 2;

    public function __construct(private ProductRepository $products) {}

    /**
     * Search-as-you-type suggestions, or null when the query is too short.
     *
     * @return Collection<int, Product>|null
     */
    public function suggestions(string $query): ?Collection
    {
        $query = $this->normalize($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return null;
        }

        return $this->products->searchSuggestions($query);
    }

    /**
     * Paginated search results, or null when the query is too short.
     */
    public function results(string $query, int $perPage): ?LengthAwarePaginator
    {
        $query = $this->normalize($query);

        if (strlen($query) < self::MIN_QUERY_LENGTH) {
            return null;
        }

        return $this->products->paginateSearch($query, $perPage);
    }

    /**
     * Join two-word spellings customers type ("i phone") into the one-word
     * product name ("iphone") so the search engine can match it exactly.
     */
    private function normalize(string $query): string
    {
        $query = mb_scrub($query, 'UTF-8');

        $normalized = preg_replace(
            [
                '/\bi\s+(phone|pad|mac|pod)(s?)\b/iu',
                '/\bair\s+(pods?|tag)\b/iu',
                '/\bmac\s+book\b/iu',
            ],
            ['i$1$2', 'air$1', 'macbook'],
            $query,
        );

        return trim($normalized ?? $query);
    }
}
