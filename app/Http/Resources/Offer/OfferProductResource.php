<?php

namespace App\Http\Resources\Offer;

use App\Models\Offer\Offer;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product card on an offer page. The offer's own discount wins over the variant price discount.
 */
class OfferProductResource extends JsonResource
{
    public function __construct(mixed $resource, private Offer $offer)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $maxRetailPrice = $this->stockVariant?->price?->max_retail_price ?? 0;
        if ($this->offerProduct?->discount_amount > 0) {
            ['percent' => $discountPercent, 'fixed' => $discountFixed] = PriceCalculator::resolveProductDiscount($this->offerProduct);
        } else {
            $discountPercent = $this->stockVariant?->price?->discount_percent ?? 0;
            $discountFixed = $this->stockVariant?->price?->discount_fixed ?? 0;
        }

        return [
            'id' => $this->id,
            'offer_id' => $this->offer->id,
            'product_id' => $this->id,
            'product_variant_id' => $this->stockVariant?->id,
            'slug' => $this->slug,
            'product_name' => $this->product_name,
            'product_image' => $this->product_image,
            'regular_price' => $maxRetailPrice,
            'final_price' => PriceCalculator::finalPrice($maxRetailPrice, $discountPercent, $discountFixed),
            'discount_amount' => PriceCalculator::discountAmount($maxRetailPrice, $discountPercent, $discountFixed),
            'discount_label' => PriceCalculator::discountLabel($discountPercent, $discountFixed),
            'is_stock' => $this->stockVariant?->stock,
            'is_prebook' => $this->stockVariant?->is_prebook,
            'purchase_point' => $this->purchase_point,
            'booking_amount' => $this->booking_amount,
            'official_warranty' => $this->has_official_warranty,
            'is_chinese' => $this->is_chinese,
            'is_unofficial' => $this->is_unofficial,
            'is_refurbished' => $this->is_refurbished,
            'is_care' => $this->cares_count > 0 ? true : false,
            'is_offer' => $this->offerProduct !== null,
            'brand' => $this->brand,
            'avg_rating' => floatval($this->reviews_avg_rating),

        ];
    }
}
