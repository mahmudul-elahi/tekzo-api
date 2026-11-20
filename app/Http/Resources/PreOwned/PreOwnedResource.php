<?php

namespace App\Http\Resources\PreOwned;

use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreOwnedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $discountPercent = $this->discount_type === 'percent' ? (float) ($this->discount_amount ?? 0) : 0;
        $discountFixed   = $this->discount_type === 'fixed'   ? (float) ($this->discount_amount ?? 0) : 0;
        $regularPrice    = (float) ($this->price ?? 0);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $product?->product_name,
            'slug' => $product?->slug,
            'is_stock' => true,
            'regular_price' => $regularPrice,
            'final_price'     => PriceCalculator::finalPrice($regularPrice, $discountPercent, $discountFixed),
            'discount_amount' => PriceCalculator::discountAmount($regularPrice, $discountPercent, $discountFixed),
            'discount_label'  => PriceCalculator::discountLabel($discountPercent, $discountFixed),
            'is_box' => $this->box,
            'charging_time' => $this->charging_time,
            'official_service_warranty' => $this->official_service_warranty,
            'attributes' => $this?->attributes?->map(function ($attribute) {
                return [
                    'name' => $attribute->attribute?->name,
                    'value' => $attribute->attributeValue?->value,
                ];
            }) ?? [],

            'product_image' => $this->thumbnail_image,
            'battery_health' => $this->battery_health,

        ];
    }
}
