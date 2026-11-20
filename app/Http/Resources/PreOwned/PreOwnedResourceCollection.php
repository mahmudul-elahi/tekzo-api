<?php

namespace App\Http\Resources\PreOwned;

use App\Enums\ProductStatus;
use App\Enums\StatusEnum;
use App\Models\Brand\Brand;
use App\Models\Page\Page;
use App\Models\PreOwned\PreOwned;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class PreOwnedResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */

    public function toArray(Request $request): array
    {
        return [
            'pre_owneds' => $this->collection->map(function ($data) {
                $preOwned = $data?->preOwned;
                return [
                    'id' => $data->id,
                    'product_name' => $data?->product_name,
                    'headline' => $data?->headline,
                    'slug' => $data?->slug,
                    'product_image' => $preOwned->thumbnail_image,
                    'regular_price' => $preOwned->price,
                    'final_price' => $preOwned->price,
                    'discount_amount' => null,
                    'discount_label' => null,
                    'is_stock' => true,
                    'total_pre_owned' => $data?->total_pre_owned ?? 0,
                    'total_review' => 0,
                    'price_range' => [
                        'min' => (float) ($data->pre_owned_min_price ?? 0),
                        'max' => (float) ($data->pre_owned_max_price ?? 0),
                    ],
                    'category' => $data?->category,
                    'brand' => $data?->brand,


                ];
            }),
            'brands' => $this->preOwnedBrand(),
            'categoryes' => $this->preOwnedCategory(),
            'seoData' => $this->seoData(),
            'pagination' => $this->buildPagination(),
        ];
    }

    private function preOwnedCategory()
    {
        $categories = PreOwned::query()
            ->whereHas('product', function ($query) {
                $query->whereIn('status', [
                    ProductStatus::Active->value,
                    ProductStatus::Comming->value
                ]);
            })
            ->with(['product' => function ($query) {
                $query->select(['id', 'category_id'])
                    ->with(['category:id,name,slug']);
            }])
            ->get()
            ->pluck('product.category')
            ->unique('id')
            ->values();

        return $categories;
    }

    private function preOwnedBrand()
    {
        $brands = Brand::query()
            ->whereHas('products', function ($query) {
                $query->whereIn('status', [
                    ProductStatus::Active->value,
                    ProductStatus::Comming->value
                ])->whereHas('preOwned');
            })
            ->where('status', StatusEnum::Active->value)
            ->select(['id', 'name', 'slug', 'primary_logo'])
            ->get();

        $brands->prepend([
            'id' => 0,
            'name' => 'All Brands',
            'slug' => 'all'
        ]);
        return $brands;
    }

    private function priceRange(): array
    {
        $range = PreOwned::query()
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        return [
            'min' => (float) ($range->min_price ?? 0),
            'max' => (float) ($range->max_price ?? 0),
        ];
    }

    private function seoData()
    {
        $page = Page::query()
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', 'used-device')
            ->select(['id', 'slug', 'title', 'description', 'tags', 'primary_logo', 'shop_banner', 'info_banner', 'tenant_id'])
            ->first();

        if (!$page) {
            return null;
        }


        $seo = DB::table('seo')
            ->where('model_id', $page->id)
            ->where('model_type', 'App\Models\Pos\Page\Page')
            ->where('tenant_id', $page->tenant_id)
            ->select(['id', 'title', 'description', 'tags'])
            ->first();

        return $seo ? [
            'title' => $seo->title,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url') . '/' . $page->slug,
            'logo' => $page->primary_logo ?? null,
            'banner' => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'siteName' => config('app.name'),
            'long_description' => $page->description
        ] : null;
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
