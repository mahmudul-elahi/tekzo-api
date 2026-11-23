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
     * A customer's reviews, newest first, with the product and the reviewed variant's attributes.
     */
    public function paginateForContact(mixed $contactId, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with(['product:id,product_name', 'orderList' => function ($query) {
                $query->with(['variant' => function ($query) {
                    $query->with(['attributeValues']);
                }]);
            }])
            ->where('contact_id', $contactId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create or update the customer's review of one order line.
     */
    public function upsertForOrderLine(mixed $contactId, mixed $productId, mixed $orderDetailId, mixed $rating, ?string $comment): Review
    {
        return $this->query()->updateOrCreate(
            [
                'contact_id' => $contactId,
                'product_id' => $productId,
                'order_detail_id' => $orderDetailId,
            ],
            [
                'rating' => $rating,
                'comment' => $comment ?? null,
            ]
        );
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
