<?php

namespace App\Http\Resources\Customer\Review;

use App\Models\Order\OrderList;
use App\Repositories\Order\OrderListRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivered order line the customer can review.
 *
 * @mixin OrderList
 */
class DeliveredLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributeValueId = $this->resource?->variant?->attributeValues->pluck('id')->first();

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'invoice_number' => $this->order->invoice_number,
            'product_name' => $this->product->product_name,
            'attributes' => self::attributeNames($this->resource),
            'image' => app(OrderListRepository::class)->primaryImagePath($this->product_id, $attributeValueId),
            'delivered_at' => $this->order->updated_at,
            'has_review' => $this->review_count,
        ];
    }

    /**
     * @return array<int, array{name: string}>
     */
    public static function attributeNames(mixed $orderList): array
    {
        return $orderList?->variant?->attributeValues ? $orderList->variant->attributeValues->map(fn ($attributeValue) => [
            'name' => $attributeValue->value,
        ])->toArray() : [];
    }
}
