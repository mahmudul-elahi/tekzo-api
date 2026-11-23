<?php

namespace App\Services\Seo;

use App\Models\Page\Page;
use App\Repositories\Page\PageSeoRepository;

/**
 * Builds the "seoData" block for CMS pages. Each endpoint family has its own
 * output shape that the frontend relies on, so each gets its own method.
 */
class PageSeoService
{
    private const array PAGE_COLUMNS = ['id', 'slug', 'title', 'description', 'tags', 'primary_logo', 'shop_banner', 'info_banner', 'tenant_id'];

    public function __construct(private readonly PageSeoRepository $pages) {}

    /**
     * SEO for shop-style pages with a fixed canonical path (shop, offers).
     * Falls back to the page title when the SEO title is empty.
     *
     * @return array{title: ?string, description: ?string, page_description: ?string, tags: ?string, canonical: string, ogImage: ?string, banner: ?string, info_banner: ?string, siteName: mixed}|null
     */
    public function forShopPage(string $slug, string $canonicalPath): ?array
    {
        $page = $this->pages->findTenantPage($slug, self::PAGE_COLUMNS);

        if (! $page instanceof Page) {
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

        if (! $page instanceof Page) {
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
     * The tenant page and its SEO row (either may be null).
     *
     * @return array{page: ?Page, seo: ?object}
     */
    public function findPageAndSeo(string $slug): array
    {
        $page = $this->pages->findTenantPage($slug, self::PAGE_COLUMNS);

        return [
            'page' => $page,
            'seo' => $page instanceof Page ? $this->pages->findSeoForPage($page) : null,
        ];
    }

    /**
     * SEO for informational pages (faq, career, standalone page SEO).
     *
     * @return array{title: ?string, description: ?string, tags: ?string, siteName: mixed, ogImage: ?string, banner: ?string, info_banner: ?string, canonicalUrl: mixed}|null
     */
    public function forInfoPage(string $slug): ?array
    {
        ['page' => $page, 'seo' => $seo] = $this->findPageAndSeo($slug);

        return $page && $seo ? $this->formatInfoPage($page, $seo) : null;
    }

    /**
     * @return array{title: ?string, description: ?string, tags: ?string, siteName: mixed, ogImage: ?string, banner: ?string, info_banner: ?string, canonicalUrl: mixed}
     */
    public function formatInfoPage(Page $page, object $seo): array
    {
        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'siteName' => config('app.name'),
            'ogImage' => $page->primary_logo ?? null,
            'banner' => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'canonicalUrl' => config('services.front_base_url'),
        ];
    }

    /**
     * SEO for the outlets page. Falls back to the page title when the SEO title is empty.
     *
     * @return array{title: ?string, description: ?string, tags: ?string, canonicalUrl: mixed, ogImage: ?string, banner: ?string, info_banner: ?string, siteName: mixed}|null
     */
    public function forOutletPage(string $slug): ?array
    {
        ['page' => $page, 'seo' => $seo] = $this->findPageAndSeo($slug);

        if (! $page || ! $seo) {
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

    /**
     * SEO for a policy page (canonical is /policy/{slug}).
     *
     * @return array{title: ?string, description: ?string, tags: ?string, canonical: string, ogImage: ?string, banner: ?string, info_banner: ?string, siteName: mixed}|null
     */
    public function forPolicyPage(string $slug): ?array
    {
        ['page' => $page, 'seo' => $seo] = $this->findPageAndSeo($slug);

        if (! $page || ! $seo) {
            return null;
        }

        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url')."/policy/{$page->slug}",
            'ogImage' => $page->primary_logo ?? null,
            'banner' => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'siteName' => config('app.name'),
        ];
    }

    /**
     * Site-wide SEO from the home page, enriched with company settings.
     *
     * @param  array<string, mixed>  $setting
     * @return array<string, mixed>|null
     */
    public function forSite(array $setting): ?array
    {
        ['page' => $page, 'seo' => $seo] = $this->findPageAndSeo('home');

        if (! $page || ! $seo) {
            return null;
        }

        $socialLinks = array_values(array_filter([
            $setting['facebook_link'] ?? null,
            $setting['instagram_link'] ?? null,
            $setting['youtube_link'] ?? null,
            $setting['linkedin_link'] ?? null,
        ]));

        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url'),
            'ogImage' => $setting['company_logo'] ?? $page->primary_logo ?? null,
            'siteName' => config('app.name'),
            'long_description' => $page->description,
            'foundingDate' => $setting['founding_date'] ?? '2025',
            'streetAddress' => $setting['primary_address'] ?? '',
            'socialLinks' => $socialLinks,

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

        if (! $page instanceof Page) {
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
