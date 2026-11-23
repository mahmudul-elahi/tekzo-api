<?php

namespace App\Services\Product;

use App\Models\Product\Product;
use App\Models\Product\Review;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ReviewRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductReviewService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ReviewRepository $reviews,
    ) {}

    /**
     * Rating summary and a page of reviews for a product.
     *
     * @return array{summary: ?Review, reviews: LengthAwarePaginator}|null null when the product doesn't exist
     */
    public function forProduct(string $slug, int $perPage): ?array
    {
        $product = $this->products->findIdBySlug($slug);

        if (! $product instanceof Product) {
            return null;
        }

        return [
            'summary' => $this->reviews->ratingSummaryForProduct($product->id),
            'reviews' => $this->reviews->paginateActiveForProduct($product->id, $perPage),
        ];
    }
}
