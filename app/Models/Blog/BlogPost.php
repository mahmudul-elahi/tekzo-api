<?php

namespace App\Models\Blog;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category_id',
    'author_id',
    'title',
    'slug',
    'excerpt',
    'content',
    'thumbnail',
    'banner',
    'reading_time',
    'views',
    'shares',
    'is_featured',
    'is_trending',
    'is_popular',
    'is_published',
    'published_at',
    'meta_title',
    'meta_description',
    'meta_keywords',
])]
class BlogPost extends Model
{
    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags()
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tags', 'post_id', 'tag_id');
    }
}
