<?php

namespace App\Http\Controllers\Faq;

use App\Http\Controllers\Controller;
use App\Models\Faq\Faq;
use App\Models\Page\Page;
use App\Models\Seo\Seo;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::query()
            ->select('id', 'group_id', 'lang', 'question', 'answer', 'order', 'updated_at')
            ->where('status', 1)
            ->whereHas('tenant', fn($q) => $q->where('slug', config('services.tenant_slug')))
            ->orderBy('order', 'asc')
            ->get()
            ->groupBy('group_id')
            ->map(fn($group) => $group->map(fn($faq) => [
                'id'       => $faq->id,
                'order'    => $faq->order,
                'lang'     => $faq->lang,
                'question' => $faq->question,
                'answer'   => $faq->answer,
            ])->values())
            ->values();

        return successResponse([
            'faqs'    => $faqs,
            'seoData' => $this->getPageWithSeo('faq'),
        ]);
    }

    protected function getPageWithSeo(string $slug)
    {
        $page = Page::query()
            ->whereHas('tenant', fn($q) => $q->where('slug', config('services.tenant_slug')))
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return [];
        }

        $seo = Seo::query()
            ->where('tenant_id', $page->tenant_id)
            ->where('model_id', $page->id)
            ->where('model_type', 'App\Models\Pos\Page\Page')
            ->first();

        if (!$seo) {
            return [];
        }

        return [
            'title'       => $seo->title,
            'description' => $seo->description,
            'tags'        => $seo->tags,
            'siteName'    => config('app.name'),
            'ogImage'     => $page->primary_logo ?? null,
            'banner'      => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'canonicalUrl' => config('services.front_base_url'),
        ];
    }
}
