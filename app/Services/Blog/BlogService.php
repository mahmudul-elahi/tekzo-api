<?php

namespace App\Services\Blog;

use App\Models\Blog\BlogPost;
use App\Models\Blog\BlogTag;
use App\Repositories\Blog\BlogPostRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BlogService
{
    private const int RELATED_COUNT = 4;

    public function __construct(private readonly BlogPostRepository $posts) {}

    public function paginatePublished(int $perPage): LengthAwarePaginator
    {
        return $this->posts->paginatePublished($perPage);
    }

    /**
     * A published post with its related posts. Counts the view.
     */
    public function findPublishedForReading(string $slug): ?BlogPost
    {
        $blog = $this->posts->findPublishedBySlug($slug);

        if (! $blog instanceof BlogPost) {
            return null;
        }

        $blog->increment('views');
        $blog->setRelation('relatedBlogs', $this->relatedTo($blog));

        return $blog;
    }

    /**
     * A published tag and a page of its posts, or null when the tag doesn't exist.
     *
     * @return array{tag: BlogTag, posts: LengthAwarePaginator}|null
     */
    public function tagWithPosts(string $slug, int $perPage): ?array
    {
        $tag = $this->posts->findPublishedTagBySlug($slug);

        if (! $tag instanceof BlogTag) {
            return null;
        }

        return [
            'tag' => $tag,
            'posts' => $this->posts->paginatePostsOfTag($tag, $perPage),
        ];
    }

    public function featured(): ?BlogPost
    {
        return $this->posts->latestFeatured();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function recent(): Collection
    {
        return $this->posts->recent();
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function popular(): Collection
    {
        return $this->posts->latestFlagged('is_popular', 3);
    }

    /**
     * @return Collection<int, BlogPost>
     */
    public function trending(): Collection
    {
        return $this->posts->latestFlagged('is_trending', 3);
    }

    /**
     * Up to 4 random posts from the same category, topped up with random posts from any category.
     *
     * @return Collection<int, BlogPost>
     */
    private function relatedTo(BlogPost $blog): Collection
    {
        $matched = $this->posts->randomPublishedInCategory($blog, self::RELATED_COUNT);
        $needed = self::RELATED_COUNT - $matched->count();

        if ($needed <= 0) {
            return $matched;
        }

        $excludeIds = $matched->pluck('id')->push($blog->id)->toArray();

        return $matched->concat($this->posts->randomPublishedExcept($excludeIds, $needed));
    }
}
