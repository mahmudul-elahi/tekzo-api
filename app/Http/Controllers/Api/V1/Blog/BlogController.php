<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Blog\BlogPostDetailResource;
use App\Http\Resources\Blog\BlogPostResource;
use App\Services\Blog\BlogService;
use App\Services\Seo\PageSeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(
        private BlogService $blogService,
        private PageSeoService $pageSeo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return successResponse($this->blogService->paginatePublished(clampPerPage($request->input('per_page'), 12, 48)));
    }

    public function show(string $slug): JsonResponse
    {
        $blog = $this->blogService->findPublishedForReading($slug);

        if (! $blog) {
            return errorResponse('Blog post not found', 404);
        }

        return successResponse(new BlogPostDetailResource($blog));
    }

    public function postsByTag(string $slug, Request $request): JsonResponse
    {
        $result = $this->blogService->tagWithPosts($slug, clampPerPage($request->input('per_page'), 12, 48));

        if (! $result) {
            return errorResponse('Tag not found', 404);
        }

        ['tag' => $tag, 'posts' => $posts] = $result;

        return successResponse([
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ],
            'posts' => $posts,
        ]);
    }

    public function featuredBlog(): JsonResponse
    {
        $featuredBlog = $this->blogService->featured();

        if (! $featuredBlog) {
            return errorResponse('No featured blog found', 404);
        }

        return successResponse([
            'featured_blog' => new BlogPostResource($featuredBlog),
            'seoData' => $this->pageSeo->forPage('blog'),
        ]);
    }

    public function recentBlog(): JsonResponse
    {
        $recentBlogs = $this->blogService->recent();

        if ($recentBlogs->isEmpty()) {
            return errorResponse('No recent blogs found', 404);
        }

        return successResponse(BlogPostResource::collection($recentBlogs));
    }

    public function popularBlog(): JsonResponse
    {
        $popularBlogs = $this->blogService->popular();

        if ($popularBlogs->isEmpty()) {
            return errorResponse('No popular blogs found', 404);
        }

        return successResponse(BlogPostResource::collection($popularBlogs));
    }

    public function trendingBlog(): JsonResponse
    {
        $trendingBlogs = $this->blogService->trending();

        if ($trendingBlogs->isEmpty()) {
            return errorResponse('No trending blogs found', 404);
        }

        return successResponse(BlogPostResource::collection($trendingBlogs));
    }
}
