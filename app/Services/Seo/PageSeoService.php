<?php

namespace App\Services\Seo;

use App\Repositories\Page\PageSeoRepository;

/**
 * Builds the "seoData" block for CMS pages. Each endpoint family has its own
 * output shape that the frontend relies on, so each gets its own method.
 */
class PageSeoService
{
    public function __construct(private PageSeoRepository $pages) {}

    /**
     * SEO for listing pages (home sections, categories, brands, news, blog).
     *
     * @return array{title: ?string, description: ?string, page_description: ?string, tags: ?string, canonical: string, logo: ?string, banner: ?string, info_banner: ?string, siteName: mixed}|null
     */
    public function forPage(string $slug): ?array
    {
        $page = $this->pages->findTenantPage($slug, ['id', 'slug', 'title', 'description', 'tags', 'primary_logo', 'shop_banner', 'info_banner', 'tenant_id']);

        if (! $page) {
            return null;
        }

        $seo = $this->pages->findSeoForPage($page);

        return $seo ? [
            'title' => $seo->title,
            'description' => $seo->description,
            'page_description' => $page->description ?? null,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url').'/'.$page->slug,
            'logo' => $page->primary_logo ?? null,
            'banner' => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'siteName' => config('app.name'),
        ] : null;
    }
}
