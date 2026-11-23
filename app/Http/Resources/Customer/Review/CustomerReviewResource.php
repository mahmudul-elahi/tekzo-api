<?php

namespace App\Http\Resources\Customer\Review;

use App\Models\Product\Review;
use App\Repositories\Order\OrderListRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the customer's reviews, with the reviewed product and its variant image.
 *
 * @mixin Review
 */
class CustomerReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributeValueId = $this->resource?->orderList?->variant?->attributeValues->pluck('id')->first();

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'review_image' => $this->resource->getImageUrlAttribute(),
            'product' => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->product_name,
                'attribute' => $this->resource?->orderList?->variant?->attributeValues->pluck('value')->implode('/'),
                'image' => app(OrderListRepository::class)->primaryImagePath($this->product_id, $attributeValueId),
            ] : null,
        ];
    }
}
