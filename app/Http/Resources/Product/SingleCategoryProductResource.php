<?php

namespace App\Http\Resources\Product;

use App\Http\Resources\Concerns\ResolvesProductCoupon;
use App\Models\Seo\Seo;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SingleCategoryProductResource extends JsonResource
{
    use ResolvesProductCoupon;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    //public static $wrap = null;
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'description' => $this->longDescription?->description,
            'info_banner' => $this->info_banner,
            'brands' => $this->getBrand(),
            'products' => $this->formattedProducts(),
            'pagination' => $this->paginationMeta(),
            'seoData' => $this->seoData(),
        ];
    }

    public function getBrand(): \Illuminate\Database\Eloquent\Collection|array
    {
        $brands = $this->available_brands ?? collect();

        $brands->prepend([
            'id' => 0,
            'name' => 'All Products',
            'slug' => 'all'
        ]);

        return $brands;
    }

    private function paginationMeta(): ?array
    {
        if (!$this->products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            return null;
        }

        return [
            'current_page' => $this->products->currentPage(),
            'last_page' => $this->products->lastPage(),
            'per_page' => $this->products->perPage(),
            'total' => $this->products->total(),
        ];
    }

    private function formattedProducts(): array
    {
        if ($this->products->isEmpty()) {
            return [];
        }

        return $this->products->map(function ($product) {

            $price = optional($product->stockVariant?->price);
            $maxRetailPrice = $price->max_retail_price ?? 0;

            if ($product->offerProduct?->discount_amount > 0) {
                ['percent' => $discountPercent, 'fixed' => $discountFixed] = PriceCalculator::resolveProductDiscount($product->offerProduct);
            } else {
                $discountPercent = $price->discount_percent ?? 0;
                $discountFixed   = $price->discount_fixed ?? 0;
            }

            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'headline' => $product->headline,
                'slug' => $product->slug,
                'product_image' => $product->product_image,
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
                'purchase_point'    => $product->purchase_point,
                'booking_amount'    => $product->booking_amount,
                'official_warranty' => $product->has_official_warranty,
                'is_chinese'        => $product->is_chinese,
                'is_unofficial'     => $product->is_unofficial,
                'is_refurbished'    => $product->is_refurbished,
                'is_care'           => $product->cares_count > 0 ? true : false,
                'is_offer'          => $product->offerProduct !== null,
                'is_stock'          => $product?->stockVariant?->stock,
                'is_prebook'        => $product->stockVariant?->is_prebook,
                'gift' => $product->gift_product_count > 0 ? true : false,
                'avg_rating'      => floatval($product->reviews_avg_rating),
                'brand'             => $product->brand,
                'coupon'            => $this->resolveCoupon($product),
            ];
        })->values()->toArray();
    }


    private function seoData()
    {
        $seo = Seo::query()
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('model_id', $this->id)
            ->where('model_type', 'App\Models\Landlord\Category\Category')
            ->first();

        if (!$seo) {
            return [
                'title' => $this->name,
                'description' => '',
                'tags' => '',
                'canonical' => config('services.front_base_url') . '/categories/' . $this->slug,
                'ogImage' => $this->logo,
                'siteName' => config('app.name'),

            ];
        }

        return [
            'title' => $seo->title ?? $this->name,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url') . '/categories/' . $this->slug,
            'ogImage' => $this->logo,
            'siteName' => config('app.name'),
        ];
    }
}
