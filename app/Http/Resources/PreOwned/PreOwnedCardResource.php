<?php

namespace App\Http\Resources\PreOwned;

use App\Models\PreOwned\PreOwned;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One pre-owned unit as a card (model listing pages and grouped shelves).
 *
 * @mixin PreOwned
 */
class PreOwnedCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discountPercent = $this->discount_type === 'percent' ? (float) ($this->discount_amount ?? 0) : 0;
        $discountFixed = $this->discount_type === 'fixed' ? (float) ($this->discount_amount ?? 0) : 0;
        $regularPrice = (float) ($this->price ?? 0);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $this->product?->product_name,
            'headline' => $this->product?->headline,
            'slug' => $this->product?->slug,
            'product_image' => $this->thumbnail_image,
            'battery_health' => $this->battery_health,
            'description' => $this->description,
            'short_description' => $this->product?->short_description,
            'regular_price' => $regularPrice,
            'final_price' => PriceCalculator::finalPrice($regularPrice, $discountPercent, $discountFixed),
            'discount_amount' => PriceCalculator::discountAmount($regularPrice, $discountPercent, $discountFixed),
            'discount_label' => PriceCalculator::discountLabel($discountPercent, $discountFixed),
            'is_box' => $this->box,
            'is_stock' => ! (bool) $this->is_sold,
            'charging_time' => $this->charging_time,
            'official_service_warranty' => $this->official_service_warranty,

            'attributes' => $this->attributes?->map(fn ($attribute) => [
                'name' => $attribute->attribute?->name,
                'value' => $attribute->attributeValue?->value,
            ]) ?? [],

        ];
    }
}
