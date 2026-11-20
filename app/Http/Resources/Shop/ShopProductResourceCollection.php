<?php

namespace App\Http\Resources\Shop;

use App\Enums\ProductStatus;
use App\Http\Resources\Concerns\ResolvesProductCoupon;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ShopProductResourceCollection extends ResourceCollection
{
    use ResolvesProductCoupon;
    public function toArray(Request $request): array
    {
        return [
            'data'       => $this->collection->map(fn($product) => $this->transformProduct($product)),
            'pagination' => $this->buildPagination(),
        ];
    }

    private function transformProduct($product): array
    {
        $maxRetailPrice = $product->stockVariant?->price?->max_retail_price ?? 0;

        $offerProductDiscount = $product->offerProduct?->discount_amount;

        if ($offerProductDiscount > 0) {
            ['percent' => $discountPercent, 'fixed' => $discountFixed] = PriceCalculator::resolveProductDiscount($product->offerProduct);
        } else {
            $discountPercent = $product->stockVariant?->price?->discount_percent ?? 0;
            $discountFixed   = $product->stockVariant?->price?->discount_fixed ?? 0;
        }

        return [
            'id'             => $product->id,
            'slug'           => $product->slug,
            'product_name'   => $product->product_name,
            'product_image'  => $product->product_image,
            'headline'       => $product->headline,
            'is_stock'       => $product->stockVariant?->stock,
            'is_prebook'     => $product->stockVariant?->is_prebook,
            'regular_price'  => $maxRetailPrice,
            'final_price'    => PriceCalculator::finalPrice($maxRetailPrice, $discountPercent, $discountFixed),
            'discount_amount' => PriceCalculator::discountAmount($maxRetailPrice, $discountPercent, $discountFixed),
            'discount_label' => PriceCalculator::discountLabel($discountPercent, $discountFixed),
            'product_badge'  => $this->resolveProductBadge($product),
            'purchase_point'    => $product->purchase_point,
            'booking_amount'    => $product->booking_amount,
            'official_warranty' => $product->has_official_warranty,
            'is_chinese'        => $product->is_chinese,
            'is_unofficial'     => $product->is_unofficial,
            'is_refurbished'    => $product->is_refurbished,
            'is_care'           => $product->cares_count > 0 ? true : false,
            'is_offer'          => $product->offerProduct !== null,
            'category'          => $product->category,
            'gift' => $product->gift_product_count > 0 ? true : false,
            'avg_rating'      => floatval($product->reviews_avg_rating),
            'created_at'        => $product->created_at,
            'coupon'            => $this->resolveCoupon($product),
        ];
    }

    private function resolveDiscount($product): array
    {
        $offerProduct   = $product->offerProduct;
        $discountType   = $offerProduct?->discount_type;
        $discountAmount = $offerProduct?->discount_amount ?? 0;

        return [
            'percent' => $discountType === 'percentage' ? $discountAmount : 0,
            'fixed'   => $discountType === 'fixed'      ? $discountAmount : 0,
        ];
    }

    private function resolveProductBadge($product): ?string
    {
        return match (true) {
            $product->status   === ProductStatus::Comming  => 'Coming Soon',
            $product->a_status === ProductStatus::Featured => 'Featured',
            $product->created_at >= now()->subDays(30)     => 'New Arrival',
            default                                        => null,
        };
    }

    private function buildPagination(): array
    {
        return [
            'total'         => $this->resource->total(),
            'per_page'      => $this->resource->perPage(),
            'current_page'  => $this->resource->currentPage(),
            'last_page'     => $this->resource->lastPage(),
            'from'          => $this->resource->firstItem(),
            'to'            => $this->resource->lastItem(),
            'next_page_url' => $this->resource->nextPageUrl(),
            'prev_page_url' => $this->resource->previousPageUrl(),
        ];
    }
}
