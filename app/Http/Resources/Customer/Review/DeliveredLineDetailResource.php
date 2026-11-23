<?php

namespace App\Http\Resources\Customer\Review;

use App\Models\Order\OrderList;
use App\Repositories\Order\OrderListRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderList
 */
class DeliveredLineDetailResource extends JsonResource
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
            'product_name' => $this->product?->product_name,
            'attributes' => DeliveredLineResource::attributeNames($this->resource),
            'image' => app(OrderListRepository::class)->primaryImagePath($this->product_id, $attributeValueId),
            'delivered_at' => $this->order->updated_at,
        ];
    }
}
