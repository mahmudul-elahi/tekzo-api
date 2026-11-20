<?php

namespace App\Http\Controllers\Outlet;

use App\Http\Controllers\Controller;
use App\Models\Outlet\Outlet;
use App\Models\Page\Page;
use App\Models\Seo\Seo;

class OutletController extends Controller
{

    public function index()
    {
        $outlets = Outlet::query()
            ->with(['images' => function ($query) {
                $query->select('id', 'image_url', 'alt_text', 'outlet_id')->orderBy('sort_order', 'asc');
            }])
            ->where('status', 1)
            ->get()->map(function ($outlet) {
                return [
                    'id' => $outlet->id,
                    'name' => $outlet->name,
                    'slug' => $outlet->slug,
                    'address' => $outlet->address,
                    'google_map_url' => $outlet->google_map_url,
                    'image' => $outlet->image,
                    'opens' => $outlet->opens,
                    'closes' => $outlet->closes,
                    'close_day' => $outlet->close_day
                ];
            });

        return successResponse([
            'outlets' => $outlets,
            'seoData' => $this->getPageWithSeo('outlets'),
        ]);
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
            'tags' => $seo->tags,
            'canonicalUrl' => config('services.front_base_url'),
            'ogImage' => $page->primary_logo,
            'banner' => $page->shop_banner,
            'info_banner' => $page->info_banner,
            'siteName' => config('app.name'),
        ];
    }

    public function outletDetail($slug)
    {
        $outlet = Outlet::query()
            ->with(['images' => function ($query) {
                $query->select('id', 'image_url', 'alt_text', 'outlet_id')->orderBy('sort_order', 'asc');
            }])
            ->where('slug', $slug)
            ->where('status', 1)
            ->first();

        if (!$outlet) {
            return errorResponse('Outlet not found', 404);
        }

        return successResponse($outlet);
    }
}
