<?php

namespace App\Repositories\Page;

use App\Models\Page\Page;
use Illuminate\Support\Facades\DB;

class PageSeoRepository
{
    /**
     * The model_type the POS stores for page SEO rows.
     */
    private const PAGE_MODEL_TYPE = 'App\Models\Pos\Page\Page';

    /**
     * A page of the current tenant (services.tenant_slug) by slug.
     *
     * @param  array<int, string>  $columns
     */
    public function findTenantPage(string $slug, array $columns = ['*']): ?Page
    {
        return Page::query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', $slug)
            ->select($columns)
            ->first();
    }

    /**
     * The SEO row the POS stored for a page.
     *
     * @return object{id: int, title: ?string, description: ?string, tags: ?string}|null
     */
    public function findSeoForPage(Page $page): ?object
    {
        return DB::table('seo')
            ->where('model_id', $page->id)
            ->where('model_type', self::PAGE_MODEL_TYPE)
            ->where('tenant_id', $page->tenant_id)
            ->select(['id', 'title', 'description', 'tags'])
            ->first();
    }
}
