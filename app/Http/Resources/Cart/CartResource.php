<?php

namespace App\Http\Resources\Cart;

use App\Models\Coupon\NewCoupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'totalBookingAmount' => floatval($this->total_booking_amount),
            'subTotal' => floatval($this->sub_total),
            'grandTotal' => floatval($this->grand_total),
            'couponDiscount' => floatval($this->coupon_discount ?? 0),
            'couponCode' => $this->coupon_id
                ? NewCoupon::find($this->coupon_id)?->code
                : null,
            'cartItems' => $this->cartItems ? $this->cartItems->map(fn ($item) => [
                'id' => $item->id,
                'isSelect' => $item->is_select,
                'product' => [
                    'name' => $item->product?->product_name,
                    'image' => $this->productImageHandel($item),
                ],
                'isPreOwned' => (bool) $item->preOwned,
                'brand' => $item->product?->brand,
                'category' => $item->product?->category,
                'quantity' => $item->quantity,
                'discountType' => $item->discount_type,
                'discountAmount' => floatval($item->discount_amount ?? 0),
                'maxRetailPrice' => $item->preOwned ? floatval($item->preOwned->price) : floatval($item->unit_price),
                'total' => $this->manageDiscountPrice($item) * $item->quantity,
                'variant' => $item->variant ? $this->manageVariant($item->variant) : [],
                'giftProduct' => $item->giftProductVariant ? $this->manageGiftProduct($item->giftProductVariant) : null,
                'bookingAmount' => floatval($item->booking_amount ?? 0),
                'productCare' => $item->pre_owned_id
                    ? ($item->preOwnedCare ? $this->manageProductCare($item->preOwnedCare) : null)
                    : ($item->productCare ? $this->manageProductCare($item->productCare) : null),
            ]) : [],
        ];
    }

    private function manageProductCare($productCare)
    {
        return [
            'id' => $productCare->id,
            'name' => $productCare->care->name,
            'value' => $productCare->care->value,
        ];
    }

    private function manageGiftProduct($giftProductVariant)
    {

        if (empty($giftProductVariant->productVariant)) {
            return null;
        }

        return [
            'id' => $giftProductVariant->id,
            'product_name' => $giftProductVariant->productVariant->product->product_name,
            'product_image' => $giftProductVariant->productVariant->product->product_image,
            'variants' => $giftProductVariant->productVariant ? $this->manageVariant($giftProductVariant->productVariant) : [],
        ];
    }

    private function productImageHandel($item)
    {
        if ($item->preOwned) {
            return $item->preOwned?->thumbnail_image;
        }

        return $item->variant ? $this->manageVariantColorImage($item->variant) : $item->product?->product_image;
    }

    private function manageVariantColorImage($productVariant)
    {
        $colorAttributeValue = $productVariant->attributeValues->first(fn ($attr) => strtolower($attr->attribute->name) === 'color');

        $featuredImage = $colorAttributeValue
            ? $productVariant->product->images->where('attribute_value_id', $colorAttributeValue->id)
                ->where('is_primary', true)
                ->first()
            : null;

        return $featuredImage?->image_path;
    }

    private function manageVariant($productVariant)
    {
        return $productVariant->attributeValues->map(fn ($attributeValue) => [
            'id' => $attributeValue->id,
            'variant_id' => $productVariant->id,
            'sku' => $productVariant->sku,
            'name' => $attributeValue->attribute?->name,
            'value' => $attributeValue->value,
        ]);
    }

    private function manageDiscountPrice($item)
    {
        $unitPrice = floatval($item->unit_price);

        if ($item->discount_type) {
            if ($item->discount_type === 'percent') {
                return $unitPrice - ($unitPrice * $item->discount_amount / 100);
            }
            if ($item->discount_type === 'fixed') {
                return max($unitPrice - floatval($item->discount_amount), 0);
            }
        }

        return $unitPrice;
    }
}
