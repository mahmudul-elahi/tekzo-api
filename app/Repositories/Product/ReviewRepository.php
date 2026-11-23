<?php

namespace App\Repositories\Product;

use App\Enums\StatusEnum;
use App\Models\Product\Review;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReviewRepository extends BaseRepository
{
    public function __construct(Review $review)
    {
        $this->model = $review;
    }

    /**
     * Count, average and per-star totals of a product's active reviews.
     */
    public function ratingSummaryForProduct(int $productId): ?Review
    {
        return $this->query()
            ->where('product_id', $productId)
            ->where('status', StatusEnum::Active)
            ->selectRaw('
            COUNT(*) as total_reviews,
            ROUND(AVG(rating),1) as avg_rating,
            SUM(rating = 5) as five_star,
            SUM(rating = 4) as four_star,
            SUM(rating = 3) as three_star,
            SUM(rating = 2) as two_star,
            SUM(rating = 1) as one_star
        ')
            ->first();
    }

    /**
     * A product's active reviews, newest first.
     */
    public function paginateActiveForProduct(int $productId, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with('contact:id,name')
            ->where('product_id', $productId)
            ->where('status', StatusEnum::Active)
            ->latest('id')
            ->paginate($perPage);
    }
}
