<?php

namespace App\Http\Resources\Product;

use App\Http\Resources\Concerns\ResolvesProductCoupon;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SinglePreOwnedProductResource extends JsonResource
{
    use ResolvesProductCoupon;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;

        $regularPrice    = (float) ($this->price ?? 0);
        $discountPercent = $this->discount_type === 'percent' ? (float) ($this->discount_amount ?? 0) : 0;
        $discountFixed   = $this->discount_type === 'fixed'   ? (float) ($this->discount_amount ?? 0) : 0;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'product_name' => $product->product_name,
            'headline' => $product->headline,
            'slug' => $product->slug,
            'product_image' => $this->thumbnail_image,
            'short_description' => $product->short_description,
            'long_description' => $this->description ?? $product->getDescription(),
            'regular_price'   => $regularPrice,
            'final_price'     => PriceCalculator::finalPrice($regularPrice, $discountPercent, $discountFixed),
            'discount_amount' => PriceCalculator::discountAmount($regularPrice, $discountPercent, $discountFixed),
            'discount_label'  => PriceCalculator::discountLabel($discountPercent, $discountFixed),
            'battery_health' => $this->battery_health,
            'is_stock' => $this->is_sold ? false : true,
            'warranty_text' => null,
            'purchase_point' => $this->purchase_point,
            'booking_amount' => $this->booking_amount,
            'is_box' => $this->box,
            'charging_time' => $this->charging_time,
            'official_service_warranty' => $this->official_service_warranty,
            'total_review' => 0,
            'avg_rating' => 0,
            'brand' => $product->brand,

            'category' => $product?->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug
            ] : null,

            'breadcrumbs' => $product->category ? $this->getBreadcrumbs($product->category) : [],

            'images' => $this->images ? $this->images->map(function ($item) {
                return [
                    'id' => $item->id,
                    'image_path' => $item->image
                ];
            }) : [],


            'coupon' => $this->resolvePreOwnedCoupon($this->resource),
            'cares'  => $this->cares?->map(function ($care) {
                $careData = $care->care;
                $percent  = (float) ($careData?->value ?? 0);
                return [
                    'id'      => $care->care_id,
                    'name'    => $careData?->name,
                    'amounts' => [[
                        'care_id'    => $care->id,
                        'variant_id' => $this->product_variant_id,
                        'amount'     => round(($this->price * $percent) / 100, 2),
                    ]],
                ];
            })->values() ?? [],
            'gift_products' => $this->gift?->variants?->map(fn($variant) => [
                'id'       => $variant->id,
                'gift_id'  => $variant->id,
                'quantity' => $variant->qty,
                'product'  => $variant->product?->only(['id', 'product_name', 'slug', 'product_image']),
            ])->values() ?? [],
            'variants' => $this->handelVariants(),
            'specifications' => $this->formattedSpecifications($product?->specificationItems ?? []),
            'seoData' => $this->getSeoData($product),

        ];
    }

    private function getSeoData($product): array
    {

        $seo = $product?->preOwnedSeo ?? null;

        $availability = $this?->is_sold ? 'OutOfStock' : 'InStock';

        $brand = [
            'name' => $product->brand?->name,
            'logo' => $product->brand?->primary_logo,
            'url'  => config('services.front_base_url') . '/brands/' . $product->brand?->slug,
        ];

        $ratingValue = round($product->reviews_avg_rating ?? 0, 1);
        $reviewCount = $product->reviews_count ?? 0;

        $base = [
            'productName'  => $product->product_name,
            'canonical'    => config('services.front_base_url') . '/used-device/product/' . $product->slug . "?preOwnedId=" . $this->id,
            'ogImage'      => $this->thumbnail_image,
            'siteName'     => config('app.name'),
            'brand'        => $brand,
            'price'        => (float) ($this->price ?? 0),
            'currency'     => 'BDT',
            'isUsed'       => "Pre-Owned",
            'availability' => $availability,
            'ratingValue'  => $ratingValue,
            'reviewCount'  => $reviewCount,
        ];

        if (!$seo) {
            return array_merge($base, [
                'title'       => $this->product_name,
                'description' => $this->product_name,
                'tags'        => null,
            ]);
        }

        return array_merge($base, [
            'title'       => $seo->title ?? $this->product_name,
            'description' => $seo->description,
            'tags'        => $seo->tags,
        ]);
    }


    private function getBreadcrumbs($category): array
    {
        $currentCategory = $category;

        if (!$currentCategory) {
            return [];
        }

        return array_map(fn($ancestor) => [
            'id'   => $ancestor->id,
            'name' => $ancestor->name,
            'slug' => $ancestor->slug,
        ], $currentCategory->ancestors());
    }

    private function handelVariants()
    {
        if (empty($this->attributes)) {
            return [];
        }
        return $this->attributes->map(function ($item) {
            return $item->attribute ? [
                'id' => $item->attribute->id,
                'name' => $item->attribute->name,
                'value' => $item?->attributeValue?->value
            ] : null;
        })->values()->toArray();
    }

    private function formattedSpecifications($specificationItems): array
    {
        return $specificationItems
            ->groupBy(fn($item) => optional($item->parent)->id)
            ->map(function ($items) {
                $group = $items->first()->parent;

                return [
                    'id'           => $group?->id,
                    'name'         => $group?->name,
                    'group_values' => $items->map(fn($item) => [
                        'id'      => $item->id,
                        'name'    => $item->sub?->name,
                        'details' => $item->details,
                    ])->values(),
                ];
            })
            ->values()
            ->toArray();
    }
}
