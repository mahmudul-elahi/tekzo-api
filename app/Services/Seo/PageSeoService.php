<?php

namespace App\Services\Seo;

use App\Repositories\Page\PageSeoRepository;

/**
 * Builds the "seoData" block for CMS pages. Each endpoint family has its own
 * output shape that the frontend relies on, so each gets its own method.
 */
class PageSeoService
{
    private const PAGE_COLUMNS = ['id', 'slug', 'title', 'description', 'tags', 'primary_logo', 'shop_banner', 'info_banner', 'tenant_id'];

    public function __construct(private PageSeoRepository $pages) {}

    /**
     * SEO for shop-style pages with a fixed canonical path (shop, offers).
     * Falls back to the page title when the SEO title is empty.
     *
     * @return array{title: ?string, description: ?string, page_description: ?string, tags: ?string, canonical: string, ogImage: ?string, banner: ?string, info_banner: ?string, siteName: mixed}|null
     */
    public function forShopPage(string $slug, string $canonicalPath): ?array
    {
        $page = $this->pages->findTenantPage($slug, self::PAGE_COLUMNS);

        if (! $page) {
            return null;
        }

        $seo = $this->pages->findSeoForPage($page);

        if (! $seo) {
            return null;
        }

        return [
            'title' => $seo->title ?? $page->title,
            'description' => $seo->description,
            'page_description' => $page->description ?? null,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url').$canonicalPath,
            'ogImage' => $page->primary_logo,
            'banner' => $page->shop_banner,
            'info_banner' => $page->info_banner,
            'siteName' => config('app.name'),
        ];
    }

    /**
     * SEO for marketplace pages (canonicalUrl is the site root).
     *
     * @return array{title: ?string, description: ?string, page_description: ?string, tags: ?string, siteName: mixed, ogImage: ?string, banner: ?string, info_banner: ?string, canonicalUrl: mixed}|null
     */
    public function forMarketplacePage(string $slug): ?array
    {
        $page = $this->pages->findTenantPage($slug, self::PAGE_COLUMNS);

        if (! $page) {
            return null;
        }

        $seo = $this->pages->findSeoForPage($page);

        if (! $seo) {
            return null;
        }

        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'page_description' => $page->description ?? null,
            'tags' => $seo->tags,
            'siteName' => config('app.name'),
            'ogImage' => $page->primary_logo ?? null,
            'banner' => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'canonicalUrl' => config('services.front_base_url'),
        ];
    }

    /**
     * SEO for listing pages (home sections, categories, brands, news, blog).
     *
     * @return array{title: ?string, description: ?string, page_description: ?string, tags: ?string, canonical: string, logo: ?string, banner: ?string, info_banner: ?string, siteName: mixed}|null
     */
    public function forPage(string $slug): ?array
    {
        $page = $this->pages->findTenantPage($slug, self::PAGE_COLUMNS);

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
