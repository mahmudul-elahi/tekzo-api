<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Http\Controllers\Controller;
use App\Models\Page\Page;
use App\Models\Seo\Seo;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function seo($pageSlug)
    {

        $page = Page::query()
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', $pageSlug)
            ->first();

        if (!$page) {
            return responseMessage('Page not found', 404);
        }


        $seo = Seo::query()
            ->where('tenant_id', $page->tenant_id)
            ->where('model_id', $page->id)
            ->where('model_type', 'App\Models\Pos\Page\Page')
            ->first();

        if (!$seo) {
            return responseMessage('Seo not found', 404);
        }

        return successResponse([
            'title' => $seo->title,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'siteName' => config('app.name'),
            'ogImage' => $page->primary_logo ?? null,
            'banner' => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'canonicalUrl' => config('services.front_base_url')
        ]);
    }
}
