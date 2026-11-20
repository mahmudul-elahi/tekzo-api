<?php

namespace App\Http\Resources\Offer;

use App\Models\Page\Page;
use App\Models\Seo\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OfferResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'offers' => $this->collection->map(function ($data) {
                return [
                    'id' => $data->id,
                    'title' => $data->title,
                    'slug' => $data->slug,
                    'description' => $data->description,
                    'banner' => $data->banner,
                    'start_at' => $data->start_at,
                    'end_at' => $data->end_at,
                ];
            }),
            'seoData' => $this->getPageWithSeo('offer'),
            'pagination' => [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
                'next_page_url' => $this->resource->nextPageUrl(),
                'prev_page_url' => $this->resource->previousPageUrl(),
            ],
        ];
    }

    protected function getPageWithSeo(string $slug)
    {
        $page = Page::query()
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', $slug)
            ->select(['id', 'title', 'slug', 'description', 'tags', 'tenant_id', 'primary_logo', 'shop_banner', 'info_banner'])
            ->first();

        if (!$page) {
            return null;
        }


        $seo = Seo::query()
            ->where('model_id', $page->id)
            ->where('model_type', 'App\Models\Pos\Page\Page')
            ->where('tenant_id', $page->tenant_id)
            ->first();

        if (!$seo) {
            return null;
        }

        return [
            'title' => $seo->title ?? $page->title,
            'description' => $seo->description,
            'page_description' => $page->description ?? null,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url') . '/shop/offer',
            'ogImage' => $page->primary_logo,
            'banner' => $page->shop_banner,
            'info_banner' => $page->info_banner,
            'siteName' => config('app.name'),
        ];
    }
}
