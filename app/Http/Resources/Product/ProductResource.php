<?php

namespace App\Http\Resources\Product;

use App\Http\Resources\Concerns\ResolvesProductCoupon;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    use ResolvesProductCoupon;
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
            $discountFixed   = $price->discount_fixed ?? 0;
        }

        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'headline' => $this->headline,
            'slug' => $this->slug,
            'product_image' => $this->product_image,
            'purchase_point' => $this->purchase_point,
            'booking_amount' => $this->booking_amount,
            'official_warranty' => $this->has_official_warranty,
            'is_chinese' => $this->is_chinese,
            'is_unofficial' => $this->is_unofficial,
            'is_refurbished' => $this->is_refurbished,
            'is_offer' => $this->offerProduct !== null,
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
            'is_prebook' => $this->stockVariant?->is_prebook,
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'brand' => $this->whenLoaded('brand', fn() => [
                'id' => $this->brand?->id,
                'name' => $this->brand?->name,
                'slug' => $this->brand?->slug,
                'logo' => $this->brand?->primary_logo,
            ]),
            'gift' => $this->gift_product_count > 0 ? true : false,
            'is_care' => $this->cares_count > 0 ? true : false,
            'avg_rating'      => floatval($this->reviews_avg_rating),
            'coupon'       => $this->resolveCoupon(),
        ];
    }
}
