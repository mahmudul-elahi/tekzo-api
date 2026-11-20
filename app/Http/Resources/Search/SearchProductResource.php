<?php

namespace App\Http\Resources\Search;

use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $maxRetailPrice = $this->stockVariant?->price?->max_retail_price ?? 0;

        $offerProductDiscount = $this->offerProduct?->discount_amount;

        if ($offerProductDiscount > 0) {
            ['percent' => $discountPercent, 'fixed' => $discountFixed] = PriceCalculator::resolveProductDiscount($this->offerProduct);
        } else {
            $discountPercent = $this->stockVariant?->price?->discount_percent ?? 0;
            $discountFixed   = $this->stockVariant?->price?->discount_fixed ?? 0;
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'product_name' => $this->product_name,
            'product_image' => $this->product_image,
            'headline' => $this->headline,
            'category' => $this->category,
            'brand' => $this->brand,
            'is_stock' => $this->stockVariant?->stock,
            'is_prebook' => $this->stockVariant?->is_prebook,
            'official_warranty' => $this->has_official_warranty,
            'is_chinese' => $this->is_chinese,
            'is_unofficial' => $this->is_unofficial,
            'is_refurbished' => $this->is_refurbished,
            'is_care' => $this->cares_count > 0 ? true : false,
            'is_offer' => $this->offerProduct !== null,
            'regular_price' => $maxRetailPrice,
            'final_price'    => PriceCalculator::finalPrice($maxRetailPrice, $discountPercent, $discountFixed),
            'discount_amount' => PriceCalculator::discountAmount($maxRetailPrice, $discountPercent, $discountFixed),
            'discount_label' => PriceCalculator::discountLabel($discountPercent, $discountFixed),
            'booking_amount' => $this->booking_amount,
        ];
    }
}
