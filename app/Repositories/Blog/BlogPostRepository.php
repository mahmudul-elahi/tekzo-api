<?php

namespace App\Repositories\Blog;

use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogTag;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BlogPostRepository extends BaseRepository
{
    private const CARD_RELATIONS = ['category:id,name', 'author:id,name', 'tags:id,name,slug'];

    public function __construct(BlogPost $blogPost)
    {
        $this->model = $blogPost;
    }

    /**
     * Published posts: pinned first (by pinned position), then newest.
     */
    public function paginatePublished(int $perPage): LengthAwarePaginator
    {
        return $this->pinnedThenNewest()->paginate($perPage);
    }

    /**
     * The 4 most recent published posts, pinned first.
     *
     * @return Collection<int, BlogPost>
     */
    public function recent(): Collection
    {
        return $this->pinnedThenNewest()->take(4)->get();
    }

    public function findPublishedBySlug(string $slug): ?BlogPost
    {
        return $this->query()
            ->with(['category:id,name,slug', 'author:id,name', 'tags:id,name,slug'])
            ->where('is_published', true)
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function randomPublishedInCategory(BlogPost $blog, int $limit): Collection
    {
        return $this->query()
            ->with(self::CARD_RELATIONS)
            ->where('category_id', $blog->category_id)
            ->where('is_published', true)
            ->where('id', '!=', $blog->id)
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    /**
     * @param  array<int, int>  $excludeIds
     * @return Collection<int, BlogPost>
     */
    public function randomPublishedExcept(array $excludeIds, int $limit): Collection
    {
        return $this->query()
            ->with(self::CARD_RELATIONS)
            ->whereNotIn('id', $excludeIds)
            ->where('is_published', true)
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    public function latestFeatured(): ?BlogPost
    {
        return $this->query()
            ->with(self::CARD_RELATIONS)
            ->where('is_published', true)
            ->where('is_featured', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * The newest published posts with the given flag set (is_popular, is_trending).
     *
     * @return Collection<int, BlogPost>
     */
    public function latestFlagged(string $flag, int $limit): Collection
    {
        return $this->query()
            ->with(self::CARD_RELATIONS)
            ->where('is_published', true)
            ->where($flag, true)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
    }

    public function findPublishedTagBySlug(string $slug): ?BlogTag
    {
        return BlogTag::query()
            ->where('slug', $slug)
            ->first();
    }

    public function paginatePostsOfTag(BlogTag $tag, int $perPage): LengthAwarePaginator
    {
        return $tag->posts()
            ->with(self::CARD_RELATIONS)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    private function pinnedThenNewest(): Builder
    {
        return $this->query()
            ->with(self::CARD_RELATIONS)
            ->where('is_published', true)
            ->orderByDesc('is_pinned')
            ->orderBy('pinned_position')
            ->orderBy('created_at', 'desc')
            ->orderByDesc('id');
    }
}
