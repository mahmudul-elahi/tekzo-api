<?php

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Blog\BlogPostDetailResource;
use App\Http\Resources\Blog\BlogPostResource;
use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogTag;
use App\Models\Page\Page;
use Illuminate\Support\Facades\DB;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = BlogPost::query()
            ->with(['category:id,name', 'author:id,name', 'tags:id,name,slug'])
            ->where('is_published', true)
            ->orderByDesc('is_pinned')
            ->orderBy('pinned_position')
            ->orderBy('created_at', 'desc')
            ->orderByDesc('id')
            ->paginate(clampPerPage(request('per_page'), 12, 48));

        return successResponse($blogs);
    }

    public function show(string $slug)
    {
        $blog = BlogPost::query()
            ->with(['category:id,name,slug', 'author:id,name', 'tags:id,name,slug'])
            ->where('is_published', true)
            ->where('slug', $slug)
            ->first();

        if (! $blog) {
            return errorResponse('Blog post not found', 404);
        }

        $blog->increment('views');
        $blog->setRelation('relatedBlogs', $this->relatedBlogs($blog));

        return successResponse(new BlogPostDetailResource($blog));
    }

    public function postsByTag(string $slug)
    {
        $tag = BlogTag::query()
            ->where('is_published', true)
            ->where('slug', $slug)->first();

        if (! $tag) {
            return errorResponse('Tag not found', 404);
        }

        $posts = $tag->posts()
            ->with(['category:id,name', 'author:id,name', 'tags:id,name,slug'])
            ->orderBy('created_at', 'desc')
            ->paginate(clampPerPage(request('per_page'), 12, 48));

        return successResponse([
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ],
            'posts' => $posts,
        ]);
    }

    /**
     * Up to 4 posts from the same category as $blog; if that category doesn't
     * have enough, the remaining slots are filled with other random posts —
     * same fallback pattern as ProductController::relatedProduct().
     */
    private function relatedBlogs(BlogPost $blog, int $limit = 4)
    {
        $withRelations = ['category:id,name', 'author:id,name'];

        $matched = BlogPost::query()
            ->with($withRelations)
            ->where('category_id', $blog->category_id)
            ->where('is_published', true)
            ->where('id', '!=', $blog->id)
            ->inRandomOrder()
            ->take($limit)
            ->get();

        $needed = $limit - $matched->count();

        if ($needed > 0) {
            $excludeIds = $matched->pluck('id')->push($blog->id)->toArray();

            $extra = BlogPost::query()
                ->with($withRelations)
                ->whereNotIn('id', $excludeIds)
                ->where('is_published', true)
                ->inRandomOrder()
                ->take($needed)
                ->get();

            return $matched->concat($extra);
        }

        return $matched;
    }

    public function featuredBlog()
    {
        $featuredBlogs = BlogPost::query()
            ->with(['category:id,name', 'author:id,name', 'tags:id,name,slug'])
            ->where('is_published', true)
            ->where('is_featured', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $featuredBlogs) {
            return errorResponse('No featured blog found', 404);
        }

        return successResponse([
            'featured_blog' => new BlogPostResource($featuredBlogs),
            'seoData' => $this->getPageWithSeo('blog'),
        ]);
    }

    public function recentBlog()
    {
        $recentBlogs = BlogPost::query()
            ->with(['category:id,name', 'author:id,name', 'tags:id,name,slug'])
            ->where('is_published', true)
            ->orderByDesc('is_pinned')          // pin করাগুলো আগে
            ->orderBy('pinned_position')        // তাদের মধ্যে আপনার ক্রম
            ->orderBy('created_at', 'desc')     // বাকি জায়গা নতুন পোস্টে ভরে
            ->orderByDesc('id')
            ->take(4)
            ->get();

        if ($recentBlogs->isEmpty()) {
            return errorResponse('No recent blogs found', 404);
        }

        return successResponse(BlogPostResource::collection($recentBlogs));
    }

    public function popularBlog()
    {

        $popularBlogs = BlogPost::query()
            ->with(['category:id,name', 'author:id,name', 'tags:id,name,slug'])
            ->where('is_published', true)
            ->where('is_popular', true)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        if ($popularBlogs->isEmpty()) {
            return errorResponse('No popular blogs found', 404);
        }

        return successResponse(BlogPostResource::collection($popularBlogs));
    }

    public function trendingBlog()
    {
        $trendingBlogs = BlogPost::query()
            ->with(['category:id,name', 'author:id,name', 'tags:id,name,slug'])
            ->where('is_published', true)
            ->where('is_trending', true)
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        if ($trendingBlogs->isEmpty()) {
            return errorResponse('No trending blogs found', 404);
        }

        return successResponse(BlogPostResource::collection($trendingBlogs));
    }

    protected function getPageWithSeo(string $slug)
    {

        $page = Page::query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', $slug)
            ->select(['id', 'slug', 'title', 'description', 'tags', 'primary_logo', 'shop_banner', 'info_banner', 'tenant_id'])
            ->first();

        if (! $page) {
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
