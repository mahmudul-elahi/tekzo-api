<?php

namespace App\Http\Resources\Wishlist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
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
            'subTotal' => floatval($this->sub_total),
            'grandTotal' => floatval($this->grand_total),
            'items' => $this->items ? $this->items->map(fn ($item) => [
                'id' => $item->id,
                'isSelect' => $item->is_select,
                'product' => [
                    'name' => $item->product?->product_name,
                    'image' => $item->variant ? $this->manageVariantColorImage($item->variant) : $item->product?->product_image,
                ],
                'brand' => $item->product?->brand,
                'category' => $item->product?->category,
                'quantity' => $item->quantity,
                'discountType' => $item->discount_type,
                'discountAmount' => floatval($item->discount_amount),
                'maxRetailPrice' => floatval($item->unit_price),
                'total' => $this->manageDiscountPrice($item) * $item->quantity,
                'variant' => $item->variant ? $this->manageVariant($item->variant) : [],
            ]) : [],
        ];
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
