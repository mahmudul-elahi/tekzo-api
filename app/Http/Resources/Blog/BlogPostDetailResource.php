<?php

namespace App\Http\Resources\Blog;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'thumbnail' => $this->thumbnail,
            'banner' => $this->banner,
            'reading_time' => $this->reading_time,
            'views' => $this->views,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'author' => $this->author ? [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ] : null,
            'tags' => $this->tags ? $this->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            }) : [],
            'is_featured' => (bool) $this->is_featured,
            'is_popular' => (bool) $this->is_popular,
            'is_trending' => (bool) $this->is_trending,
            'published_at' => $this->published_at ? Carbon::parse($this->published_at)->format('M d, Y') : null,
            'related_blogs' => BlogPostResource::collection($this->whenLoaded('relatedBlogs')),
            'seoData' => [
                'title' => $this->meta_title ?: $this->title,
                'description' => $this->meta_description ?: $this->excerpt,
                'tags' => $this->meta_keywords,
                'canonical' => config('services.front_base_url') . '/blog/' . $this->slug,
                'ogImage' => $this->thumbnail,
                'siteName' => config('app.name'),
            ],
        ];
    }
}
