<?php

namespace App\Console\Commands;

use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\News\News;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateNewsToBlogPosts extends Command
{
    protected $signature = 'app:migrate-news-to-blog-posts {--tenant=4}';
    protected $description = 'Copy News rows for a tenant into blog_posts, categorized by content, authored by user id 1';

    private const AUTHOR_ID = 1;

    /**
     * News id => blog category [slug, name]. Built by reading every tenant_id=4
     * news item's heading/description and grouping them by what they're actually about.
     */
    private const CATEGORY_MAP = [
        // Store News / Announcements
        20 => 'store-news', 21 => 'store-news', 23 => 'store-news',

        // Customer Stories
        22 => 'customer-stories', 24 => 'customer-stories', 25 => 'customer-stories', 26 => 'customer-stories',

        // Buying Guides (Pre-Owned)
        27 => 'buying-guides', 28 => 'buying-guides', 29 => 'buying-guides', 30 => 'buying-guides',
        31 => 'buying-guides', 32 => 'buying-guides', 33 => 'buying-guides', 34 => 'buying-guides',
        35 => 'buying-guides', 36 => 'buying-guides', 39 => 'buying-guides', 40 => 'buying-guides',
        41 => 'buying-guides', 46 => 'buying-guides', 47 => 'buying-guides', 50 => 'buying-guides',
        51 => 'buying-guides', 52 => 'buying-guides', 53 => 'buying-guides', 54 => 'buying-guides',
        55 => 'buying-guides',

        // Price Guides
        42 => 'price-guides', 43 => 'price-guides', 48 => 'price-guides', 49 => 'price-guides',

        // Top Picks / Roundups
        37 => 'top-picks', 38 => 'top-picks', 44 => 'top-picks', 45 => 'top-picks',

        // Brand Spotlight
        56 => 'brand-spotlight', 57 => 'brand-spotlight', 58 => 'brand-spotlight',
        59 => 'brand-spotlight', 60 => 'brand-spotlight',
    ];

    private const CATEGORY_NAMES = [
        'store-news' => 'Store News',
        'customer-stories' => 'Customer Stories',
        'buying-guides' => 'Buying Guides',
        'price-guides' => 'Price Guides',
        'top-picks' => 'Top Picks',
        'brand-spotlight' => 'Brand Spotlight',
    ];

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');

        $categories = collect(self::CATEGORY_NAMES)->mapWithKeys(function ($name, $slug) {
            $category = BlogCategory::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'status' => true]
            );

            return [$slug => $category->id];
        });

        $newsItems = News::query()->where('tenant_id', $tenantId)->get();

        $created = 0;
        $skipped = 0;

        foreach ($newsItems as $news) {
            $categorySlug = self::CATEGORY_MAP[$news->id] ?? null;

            if (!$categorySlug) {
                $this->warn("No category mapping for news id {$news->id}, skipping.");
                $skipped++;
                continue;
            }

            $slug = $this->uniqueSlug($news->heading ?: $news->name);

            if (BlogPost::query()->where('slug', $slug)->exists()) {
                $this->line("Blog post already exists for news id {$news->id} ({$slug}), skipping.");
                $skipped++;
                continue;
            }

            $plainDescription = trim(strip_tags($news->description ?? ''));

            BlogPost::query()->create([
                'category_id' => $categories[$categorySlug],
                'author_id' => self::AUTHOR_ID,
                'title' => $news->heading ?: $news->name,
                'slug' => $slug,
                'excerpt' => Str::limit($plainDescription, 300),
                'content' => $news->description ?? '',
                'thumbnail' => $news->image,
                'banner' => $news->image,
                'reading_time' => max(1, (int) ceil(str_word_count($plainDescription) / 200)),
                'is_published' => (bool) $news->status,
                'published_at' => $news->created_at,
            ]);

            $created++;
        }

        $this->info("Done. Created {$created} blog posts, skipped {$skipped}.");

        return self::SUCCESS;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (BlogPost::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . ++$suffix;
        }

        return $slug;
    }
}
