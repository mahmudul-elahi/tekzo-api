<?php

namespace App\Http\Resources\Product;

use App\Models\Product\ProductImage;
use App\Models\Seo\Seo;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class SingleProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->getFeaturedImages($this->id);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'product_name' => $this->product_name,
            'headline' => $this->headline,
            'product_image' => $this->product_image,
            'warranty_day' => $this->warranty_day
                ? (int) round($this->warranty_day / 30.44).' Months'
                : null ?? null,
            'warranty_text' => $this->warrantyText(),
            'official_warranty' => $this->has_official_warranty,
            'service_warranty' => $this->service_warranty,
            'is_refurbished' => $this->is_refurbished,
            'is_chinese' => $this->is_chinese,
            'is_unofficial' => $this->is_unofficial,
            'is_care' => $this->cares && $this->cares->isNotEmpty(),
            'is_offer' => $this->variants?->contains(fn ($variant) => $variant->offerProduct !== null) ?? false,
            'short_description' => $this->short_description,
            'long_description' => $this->getDescription(),

            'purchase_point' => $this->purchase_point,
            'booking_amount' => $this->booking_amount,
            'total_review' => $this->reviews_count,
            'avg_rating' => round($this->reviews_avg_rating ?? 0, 1),
            'total_visit' => $this->view_count,
            'brand' => $this->brand,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'breadcrumbs' => $this->getBreadcrumbs(),
            'coupon' => $this->resolved_coupon ? [
                'code' => $this->resolved_coupon->code,
                'discount_type' => $this->resolved_coupon->discount_type,
                'amount' => $this->resolved_coupon->discount_value,
            ] : null,
            'gift_products' => $this->giftProduct ? $this->managedGiftProducts() : [],
            'cares' => $this->cares ? $this->formattedCares() : [],
            'images' => $images,
            'videos' => $this->videos ? $this->formattedVideos() : [],
            'variants' => $this->formattedVariants($images),
            'specifications' => $this->formattedSpecifications(),
            'seoData' => $this->getSeoData(),
        ];
    }

    public function formattedVideos()
    {
        return $this->videos->map(function ($video) {
            return [
                'id' => $video->id,
                'video_link' => $video->video_link,
            ];
        });
    }

    private function formattedCares()
    {
        $stockedVariants = $this->variants
            ?->filter(fn ($variant) => $variant->stock === true)
            ->map(fn ($variant) => [
                'variant_id' => $variant->id,
                'price' => $variant->price?->max_retail_price ?? 0,
            ])
            ->values() ?? collect();

        return $this->cares->map(function ($care) use ($stockedVariants) {
            $careData = $care->care;
            $percent = (float) ($careData->value ?? 0);

            $amounts = $stockedVariants->map(fn ($v) => [
                'care_id' => $care->id,
                'variant_id' => $v['variant_id'],
                'amount' => round(($v['price'] * $percent) / 100, 2),
            ])->values();

            return [
                'id' => $care->care_id,
                'name' => $careData->name,
                'amounts' => $amounts,
            ];
        });
    }

    private function getBreadcrumbs(): array
    {
        $currentCategory = $this->category;

        if (! $currentCategory) {
            return [];
        }

        return array_map(fn ($ancestor) => [
            'id' => $ancestor->id,
            'name' => $ancestor->name,
            'slug' => $ancestor->slug,
        ], $currentCategory->ancestors());
    }

    private function managedGiftProducts()
    {
        if (empty($this->giftProduct->variants)) {
            return [];
        }

        return $this->giftProduct->variants->map(function ($variant) {
            return [
                'id' => $variant->id,
                'gift_id' => $variant->id,
                'quantity' => $variant->qty,
                'product' => $variant?->product,
            ];
        });
    }

    /**
     * Specifications Format
     */
    private function formattedSpecifications(): array
    {
        if (! $this->specificationItems) {
            return [];
        }

        return $this->specificationItems
            ->groupBy(fn ($item) => optional($item->parent)->id)
            ->map(function ($items) {
                $group = $items->first()->parent;

                return [
                    'id' => $group?->id,
                    'name' => $group?->name,
                    'group_values' => $items->map(fn ($item) => [
                        'id' => $item->id,
                        'name' => $item->sub?->name,
                        'details' => $item->details,
                    ])->values(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * SEO Data
     */
    private function getSeoData(): array
    {
        $seo = $this->seos
            ?->first(fn ($s) => $s->tenant?->slug === config('services.tenant_slug'));

        // Fallback: eager load  seos
        if (! isset($this->seos)) {
            $seo = Seo::query()
                ->whereHas('tenant', fn ($q) => $q->where('slug', config('services.tenant_slug')))
                ->where('model_type', 'App\Models\Pos\Product\Product')
                ->where('model_id', $this->id)
                ->first();
        }

        $displayVariant = $this->variants->firstWhere('stock', true)
            ?? $this->variants->first();

        $price = $displayVariant->price;
        $regularPrice = $price->max_retail_price ?? 0;

        $offerProductDiscount = $displayVariant->offerProduct?->discount_amount;

        if ($offerProductDiscount > 0) {
            ['percent' => $discountPercent, 'fixed' => $discountFixed] =
                PriceCalculator::resolveProductDiscount($displayVariant->offerProduct);
        } else {
            $discountPercent = $price->discount_percent ?? 0;
            $discountFixed = $price->discount_fixed ?? 0;
        }

        $isUsed = collect($displayVariant?->attributeValues ?? [])
            ->contains(fn ($attr) => in_array($attr['value'] ?? '', ['Pre-Owned', 'Used']));

        $availability = ($displayVariant?->stock > 0) ? 'InStock' : 'OutOfStock';

        $brand = [
            'name' => $this->brand?->name,
            'logo' => $this->brand?->primary_logo,
            'url' => config('services.front_base_url').'/brands/'.$this->brand?->slug,
        ];

        $ratingValue = round($this->reviews_avg_rating ?? 0, 1);
        $reviewCount = $this->reviews_count ?? 0;

        $base = [
            'productName' => $this->product_name,
            'canonical' => config('services.front_base_url').'/product/'.$this->slug,
            'ogImage' => $this->product_image,
            'siteName' => config('app.name'),
            'brand' => $brand,
            'price' => PriceCalculator::finalPrice($regularPrice, $discountPercent, $discountFixed),
            'currency' => 'BDT',
            'isUsed' => $isUsed,
            'availability' => $availability,
            'ratingValue' => $ratingValue,
            'reviewCount' => $reviewCount,
        ];

        if (! $seo) {
            return array_merge($base, [
                'title' => $this->product_name,
                'description' => $this->product_name,
                'tags' => null,
            ]);
        }

        return array_merge($base, [
            'title' => $seo->title ?? $this->product_name,
            'description' => $seo->description,
            'tags' => $seo->tags,
        ]);
    }

    /**
     * Warranty text formatting
     */
    private function warrantyText()
    {
        if ($this->warranty_text) {
            return trim(strip_tags($this->warranty_text)) ?: null;
        }

        return null;

        // return $this->warranty_day
        //     ? (int) round($this->warranty_day / 30.44) . ' Months'
        //     : null;
    }

    /**
     * Format Variants
     */
    private function formattedVariants($images)
    {
        if (! $this->variants) {
            return [];
        }

        $imageAttributeIds = collect($images)
            ->pluck('attribute.id')
            ->filter()
            ->values()
            ->toArray();

        $attributeImageMap = collect($images)
            ->keyBy(fn ($img) => $img['attribute']['id'] ?? null)
            ->filter(fn ($img, $key) => $key !== null)
            ->map(fn ($img) => $img['image_path']);

        return $this->variants->map(function ($variant) use ($imageAttributeIds, $attributeImageMap) {

            $price = $variant->price;
            $regularPrice = $price->max_retail_price ?? 0;

            $offerProductDiscount = $variant->offerProduct?->discount_amount;

            if ($offerProductDiscount > 0) {
                ['percent' => $discountPercent, 'fixed' => $discountFixed] =
                    PriceCalculator::resolveProductDiscount($variant->offerProduct);
            } else {
                $discountPercent = $price->discount_percent ?? 0;
                $discountFixed = $price->discount_fixed ?? 0;
            }

            $attribute = $variant->attributeValues
                ->first(fn ($attr) => in_array($attr->id, $imageAttributeIds));

            return [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'sku' => $variant->sku,
                'stock' => $variant->stock,
                'is_prebook' => $variant->is_prebook,
                'regular_price' => $regularPrice,
                'final_price' => PriceCalculator::finalPrice($regularPrice, $discountPercent, $discountFixed),
                'discount_amount' => PriceCalculator::discountAmount($regularPrice, $discountPercent, $discountFixed),
                'discount_label' => PriceCalculator::discountLabel($discountPercent, $discountFixed),
                'attribute_value' => $variant->attributeValues->pluck('value')->implode('/'),

                'attribute_values' => $variant->attributeValues
                    ->map(fn ($attr) => [
                        'id' => $attr->id,
                        'value' => $attr->value,
                    ]),

                'attribute' => $attribute ? [
                    'id' => $attribute->id,
                    'color' => $attribute->value,
                    'image' => $attributeImageMap->get($attribute->id, ''),
                    'images' => $this->imagesForAttributeValue($attribute->id),
                ] : null,
            ];
        })->values()->toArray();
    }

    private function getFeaturedImages($productId): Collection
    {
        return $this->attributeImages($productId)
            ->where('is_primary', true)
            ->values()
            ->map(fn ($image) => [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'attribute' => $image->attributeValue ? [
                    'id' => $image->attributeValue->id,
                    'color' => $image->attributeValue->value,
                ] : null,
            ]);
    }

    /**
     * All images attached to the product's variant attribute values, primary first, then by upload order.
     *
     * @return Collection<int, ProductImage>
     */
    private function attributeImages($productId): Collection
    {
        return once(function () use ($productId) {
            $variantAttributeValueIds = $this->variantAttributeValueIds();

            if (empty($variantAttributeValueIds)) {
                return collect();
            }

            return ProductImage::query()
                ->with('attributeValue')
                ->where('product_id', $productId)
                ->whereIn('attribute_value_id', $variantAttributeValueIds)
                ->orderByDesc('is_primary')
                ->orderBy('order')
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Gallery images for one attribute value (color).
     *
     * @return array<int, array{id: int, image_path: string, alt_text: ?string, is_primary: bool}>
     */
    private function imagesForAttributeValue(int $attributeValueId): array
    {
        return $this->attributeImages($this->id)
            ->where('attribute_value_id', $attributeValueId)
            ->map(fn ($image) => [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'alt_text' => $image->alt_text,
                'is_primary' => (bool) $image->is_primary,
            ])
            ->values()
            ->toArray();
    }

    private function variantAttributeValueIds(): array
    {
        return once(
            fn () => $this->variants
                ->flatMap(fn ($variant) => $variant->attributeValues->pluck('id'))
                ->unique()
                ->values()
                ->toArray()
        );
    }
}
