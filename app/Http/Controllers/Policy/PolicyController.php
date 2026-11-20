<?php

namespace App\Http\Controllers\Policy;

use App\Http\Controllers\Controller;
use App\Models\Page\Page;
use App\Models\Widget\Widget;
use Illuminate\Support\Facades\DB;

class PolicyController extends Controller
{
    public function index()
    {
        $widgets = Widget::query()
            ->select(['name', 'type'])
            ->whereNotIn('type', ['return-refund-policy'])
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->get();

        return successResponse($widgets);
    }

    public function policyType($type)
    {
        $widget = Widget::query()
            ->where('type', $type)
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->select(['name', 'value'])
            ->first();
        if (!$widget) {
            return errorResponse([], 404);
        }

        return successResponse([
            'name' => $widget->name,
            'value' => $widget->value,
            'seoData' => $this->getPageWithSeo($type)
        ]);
    }

    protected function getPageWithSeo(string $slug)
    {

        $page = Page::query()
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', $slug)
            ->select(['id', 'slug', 'primary_logo', 'shop_banner', 'info_banner', 'tenant_id'])
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
            'canonical' => config('services.front_base_url') . "/policy/{$page->slug}",
            'ogImage' => $page->primary_logo ?? null,
            'banner' => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'siteName' => config('app.name'),
        ] : null;
    }
}
