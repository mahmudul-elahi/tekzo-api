<?php

namespace App\Http\Resources\Category;

use App\Http\Resources\Concerns\ResolvesProductCoupon;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryProductResource extends JsonResource
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
            'name' => $this->product_name,
            'slug' => $this->slug,
            'product_image' => $this->product_image,
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
            'booking_amount' => $this->booking_amount,
            'purchase_point' => $this->purchase_point,
            'official_warranty' => $this->has_official_warranty,
            'is_chinese' => $this->is_chinese,
            'is_unofficial' => $this->is_unofficial,
            'is_refurbished' => $this->is_refurbished,
            'is_care' => $this->cares_count > 0 ? true : false,
            'is_offer' => $this->offerProduct !== null,
            'is_stock' => $this->stockVariant?->stock,
            'is_prebook' => $this->stockVariant?->is_prebook,
            'brand' => $this->brand ? [
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
                'logo' => $this->brand?->primary_logo,
            ] : null,

            'gift' => $this->gift_product_count > 0 ? true : false,
            'avg_rating'      => floatval($this->reviews_avg_rating),
            'coupon'       => $this->resolveCoupon(),
        ];
    }
}
