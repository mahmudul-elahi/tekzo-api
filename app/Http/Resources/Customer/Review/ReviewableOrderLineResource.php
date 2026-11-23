<?php

namespace App\Http\Resources\Customer\Review;

use App\Models\Order\OrderList;
use App\Repositories\Order\OrderListRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A line of a completed order, as listed for reviewing.
 *
 * @mixin OrderList
 */
class ReviewableOrderLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributeValueId = $this->variant->attributeValues->pluck('id')->first();

        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product->id,
                'product_variant_id' => $this->product_variant_id,
                'name' => $this->product->product_name,
                'attribute_value' => $this->variant->attributeValues,
                'image' => app(OrderListRepository::class)->primaryImagePath($this->product_id, $attributeValueId),
            ],
        ];
    }
}
