<?php

namespace App\Http\Resources\Reels;

use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReelsVideoProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price = optional($this->stockVariant?->price);
        $maxRetailPrice = $price->max_retail_price ?? 0;

        if ($this->offerProduct?->discount_amount > 0) {
            ['percent' => $discountPercent, 'fixed' => $discountFixed] = PriceCalculator::resolveProductDiscount($this->offerProduct);
        } else {
            $discountPercent = $price->discount_percent ?? 0;
            $discountFixed = $price->discount_fixed ?? 0;
        }

        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'headline' => $this->headline,
            'slug' => $this->slug,
            'product_image' => $this->product_image,
            'short_description' => $this->short_description,
            'regular_price' => $maxRetailPrice,
            'final_price' => PriceCalculator::finalPrice(
                $maxRetailPrice,
                $discountPercent,
                $discountFixed
            ),

            'discount_amount' => PriceCalculator::discountAmount(
                $maxRetailPrice,
                $discountPercent,
                $discountFixed
            ),

            'discount_label' => PriceCalculator::discountLabel(
                $discountPercent,
                $discountFixed
            ),
            'is_stock' => $this->stockVariant?->stock,

            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'slug' => $this->brand?->slug,
                'logo' => $this->brand?->primary_logo,
            ]),
            'total_review' => $this->reviews_count,
        ];
    }
}
