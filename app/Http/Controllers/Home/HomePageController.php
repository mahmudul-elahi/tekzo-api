<?php

namespace App\Http\Controllers\Home;

use App\Enums\ProductStatus;
use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Category\CategoryTreeResource;
use App\Http\Resources\News\NewsResource;
use App\Http\Resources\PreOwned\PreOwnedResource;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\Reels\ReelsVideoResource;
use App\Http\Resources\Search\SearchProductResource;
use App\Http\Resources\Shop\ShopProductResourceCollection;
use App\Models\Brand\Brand;
use App\Models\Category\Category;
use App\Models\News\News;
use App\Models\Offer\Offer;
use App\Models\Page\Page;
use App\Models\PreOwned\PreOwned;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Models\Reels\ReelsVideo;
use App\Models\Slider\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HomePageController extends Controller
{
    public function homeCategory()
    {
        return Category::query()
            ->select('id', 'name', 'slug', 'logo')
            ->where('status', StatusEnum::Active->value)
            ->whereHas('products', function ($query) {
                $query->whereIn('status', [
                    ProductStatus::Active->value,
                    ProductStatus::Comming->value,
                ]);
            })
            ->orderByRaw('CASE WHEN serial = 0 OR serial IS NULL THEN 1 ELSE 0 END')
            ->orderBy('serial', 'asc')
            ->limit(8)
            ->get();
    }

    public function categories()
    {
        // The category tree can be several levels deep with inconsistent depth per
        // branch, so ranking needs the full root->leaf chain of "order" values, not
        // just the immediate parent's. Build an id => [parent_id, order] map once
        // (no per-category queries) and walk it in memory for each leaf category.
        $allCategories = Category::query()
            ->select(['id', 'parent_id', 'order'])
            ->get()
            ->keyBy('id');

        $categories = Category::query()
            ->select(['id', 'name', 'title', 'slug', 'logo', 'parent_id', 'order', 'updated_at'])
            ->where('status', StatusEnum::Active->value)
            ->whereHas('products', function ($query) {
                $query->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value]);
            })
            ->whereDoesntHave('children')
            ->get()
            ->sortBy(fn ($category) => $this->categoryOrderChainKey($category, $allCategories))
            ->values();

        return successResponse($categories);
    }

    /**
     * Builds a lexicographically-sortable key from a category's full ancestor chain
     * (root -> ... -> leaf) of "order" values, so branches are ranked by their true
     * position in the tree instead of just their immediate parent's order.
     */
    private function categoryOrderChainKey(Category $category, Collection $allCategories): string
    {
        $chain = [];
        $current = $category;
        $visited = [];

        while ($current && ! isset($visited[$current->id])) {
            $visited[$current->id] = true;
            // Pair order with id at every level: two different branches that tie on
            // "order" at some ancestor level must still stay grouped separately by
            // branch, instead of their leaves interleaving on the next level's order.
            array_unshift($chain, str_pad((string) ($current->order ?? 0), 6, '0', STR_PAD_LEFT)
                .'_'.str_pad((string) $current->id, 8, '0', STR_PAD_LEFT));
            $current = $current->parent_id ? $allCategories->get($current->parent_id) : null;
        }

        $key = implode('-', $chain);

        // $current still being set means the loop stopped on a cycle (e.g. a category
        // whose parent_id points back to itself/an ancestor) rather than reaching a
        // real root. A short chain from a broken parent link would otherwise sort as a
        // string-prefix of longer, correct chains and jump ahead of them, so push it
        // to the very end instead.
        return $current ? '999999-'.$key : $key;
    }

    public function categoryTree()
    {
        $categories = Category::query()
            ->select(['id', 'parent_id', 'name', 'slug', 'logo'])
            ->whereNull('parent_id')
            ->where('status', StatusEnum::Active->value)
            ->with(['children' => function ($query) {
                $query->select(['id', 'parent_id', 'name', 'slug', 'logo'])
                    ->where('status', StatusEnum::Active->value)
                    ->with(['children' => function ($query) {
                        $query->select(['id', 'parent_id', 'name', 'slug', 'logo'])
                            ->where('status', StatusEnum::Active->value);
                    }])
                    ->orderByRaw('CASE WHEN serial = 0 OR serial IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('serial', 'asc');
            }])
            ->orderByRaw('CASE WHEN serial = 0 OR serial IS NULL THEN 1 ELSE 0 END')
            ->orderBy('serial', 'asc')
            ->get();

        return successResponse(CategoryTreeResource::collection($categories));
    }

    public function brand()
    {
        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('order', 'asc')
            ->select(['id', 'name', 'slug', 'tagline', 'shop_banner', 'primary_logo', 'secondary_logo', 'updated_at'])
            ->get();

        return successResponse($brands);
    }

    public function allBrand()
    {
        $brands = Brand::query()
            ->where('status', true)
            ->orderBy('order', 'asc')
            ->select(['id', 'name', 'slug', 'tagline', 'shop_banner', 'primary_logo', 'secondary_logo'])
            ->get();

        return successResponse([
            'brands' => $brands,
            'seoData' => $this->getPageWithSeo('brands'),
        ]);
    }

    public function categoryProducts()
    {
        // Same hierarchy-aware ordering as categories() below — a single-level
        // `order` sort ties whenever sibling categories under different parents
        // share the same order value, so ranking needs the full root->leaf chain.
        $allCategories = Category::query()
            ->select(['id', 'parent_id', 'order'])
            ->get()
            ->keyBy('id');

        $categories = Category::query()
            ->select(['id', 'name', 'title', 'slug', 'logo', 'banner', 'parent_id', 'order'])
            ->where('status', StatusEnum::Active->value)
            ->whereHas('products', function ($query) {
                $query->whereIn('status', [
                    ProductStatus::Active->value,
                    ProductStatus::Comming->value,
                ])->whereHas('brand', function ($q) {
                    $q->where('status', true);
                });
            })
            ->with([
                'products' => function ($query) {
                    // Written as a raw SQL string (not an addSelect() alias) because this
                    // relation gets wrapped in a ROW_NUMBER() OVER (...) window function
                    // for the per-category take(2) limit below, and MySQL's window ORDER
                    // BY can't reference a SELECT-list alias from the same query — it
                    // needs the full expression, same as the has_stock ORDER BY already does.
                    $displayPriceSql = "(
                        SELECT MIN(
                            CASE
                                WHEN variant_prices.discount_percent > 0 THEN variant_prices.max_retail_price - (variant_prices.max_retail_price * variant_prices.discount_percent / 100)
                                WHEN variant_prices.discount_fixed > 0   THEN variant_prices.max_retail_price - variant_prices.discount_fixed
                                ELSE variant_prices.max_retail_price
                            END
                        )
                        FROM product_variants
                        INNER JOIN variant_prices ON variant_prices.product_variant_id = product_variants.id
                        WHERE product_variants.product_id = products.id
                        AND product_variants.status = '1'
                        AND product_variants.deleted_at IS NULL
                    )";

                    $query->select([
                        'id',
                        'category_id',
                        'brand_id',
                        'product_name',
                        'slug',
                        'product_image',
                        'status',
                        'has_official_warranty',
                        'is_chinese',
                        'is_unofficial',
                        'is_refurbished',
                        'purchase_point',
                        'booking_amount',
                    ])
                        ->addSelect([
                            'display_price' => DB::raw($displayPriceSql),
                        ])
                        ->whereIn('status', [
                            ProductStatus::Active->value,
                            ProductStatus::Comming->value,
                        ])
                        ->whereHas('brand', function ($q) {
                            $q->where('status', true);
                        })
                        ->with([
                            'brand:id,name,slug,primary_logo',
                            'offerProduct',
                            'stockVariant' => function ($q) {
                                $q->where('status', '1')
                                    ->orderByLowestPrice()
                                    ->with('price');
                            },
                        ])
                        ->withCount(['giftProduct', 'cares'])
                        ->withAvg('reviews', 'rating')
                        ->orderByRaw('(SELECT IF(MAX(stock) > 0, 1, 0) FROM product_variants WHERE product_id = products.id AND status = "1" AND deleted_at IS NULL) DESC')
                        ->orderByRaw("$displayPriceSql DESC")
                        ->orderByDesc('products.created_at')
                        ->take(2);
                },
            ])
            ->get()
            ->sortBy(fn ($category) => $this->categoryOrderChainKey($category, $allCategories))
            ->values();

        return successResponse([
            'categories' => CategoryResource::collection($categories),
            'seoData' => $this->getPageWithSeo('categories'),
        ]);
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

    public function slider()
    {
        $baseSliderQuery = Slider::query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('is_active', StatusEnum::Active->value)
            ->select([
                'id',
                'heading',
                'url',
                'image',
                'banner_feature',
            ]);

        $sliders = (clone $baseSliderQuery)
            ->where('status', StatusEnum::Active->value)
            ->orderBy('numbering')
            ->get();

        $banners = (clone $baseSliderQuery)
            ->where('status', StatusEnum::Bannar->value)
            ->whereNull('banner_feature')
            ->get();

        $bannerFeatures = (clone $baseSliderQuery)
            ->whereIn('banner_feature', ['flash_sell', 'coming_soon', 'new_feature', 'home_large_banner'])
            ->get()
            ->keyBy('banner_feature');

        $flashSellBanner = $bannerFeatures->get('flash_sell');
        $newFeatureBanner = $bannerFeatures->get('new_feature');
        $comingSoonBanner = $bannerFeatures->get('coming_soon');
        $homePageLargeBanner = $bannerFeatures->get('home_large_banner');

        $fourCardBanners = (clone $baseSliderQuery)
            ->whereIn('banner_feature', ['four_card'])
            ->get();

        $threeCardBanners = (clone $baseSliderQuery)
            ->whereIn('banner_feature', ['three_card'])
            ->get();

        return successResponse([
            'sliders' => $sliders,
            'banners' => $banners,
            'flashSellBanner' => $flashSellBanner,
            'newFeatureBanner' => $newFeatureBanner,
            'comingSoonBanner' => $comingSoonBanner,
            'fourCardBanners' => $fourCardBanners,
            'threeCardBanners' => $threeCardBanners,
            'homePageLargeBanner' => $homePageLargeBanner,
        ]);
    }

    public function flashSaleProduct()
    {

        $flashSaleProducts = Product::query()
            ->select('id', 'product_name', 'slug', 'headline', 'product_image', 'category_id', 'is_refurbished')
            ->where('status', ProductStatus::Active->value)

            ->with(['category:id,name,slug', 'stockVariant.price'])
            ->withCount('reviews')
            ->limit(4)
            ->get();

        return successResponse(ProductResource::collection($flashSaleProducts));
    }

    public function flashSale()
    {
        $flashSales = Offer::query()
            ->where('is_active', 1)
            ->where('type', 'flash_sale')
            ->latest()
            ->first();

        if (! $flashSales) {
            return successResponse(['products' => [], 'offer' => null]);
        }

        $products = Product::query()
            ->whereHas('offerProduct', function ($query) use ($flashSales) {
                $query->where('offer_id', $flashSales->id);
            })
            ->where('status', ProductStatus::Active->value)
            ->addSelect([
                'has_stock' => ProductVariant::selectRaw('MAX(stock)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', '1')
                    ->limit(1),
            ])
            ->with(['category:id,name,slug', 'offerProduct' => function ($query) use ($flashSales) {
                $query->where('offer_id', $flashSales->id);
            }, 'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->with(['price']);
            }])

            ->withCount(['offerProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->orderByRaw('has_stock DESC')
            ->orderBy('numbering', 'asc')
            ->get();

        return successResponse([
            'products' => ProductResource::collection($products),
            'offer' => [
                'id' => $flashSales->id,
                'slug' => $flashSales->slug,
                'title' => $flashSales->title,
                'banner' => $flashSales->banner,
                'start_at' => $flashSales->start_at,
                'end_at' => $flashSales->end_at,
            ],
            'seoData' => $this->getPageWithSeo('flash-sale'),
        ]);
    }

    public function featuredProduct()
    {
        $featuredProducts = Product::query()
            ->select('id', 'slug', 'product_name', 'headline', 'product_image', 'category_id', 'has_official_warranty', 'is_chinese', 'is_unofficial', 'is_refurbished', 'purchase_point', 'booking_amount')
            ->where('status', ProductStatus::Active->value)
            ->where('a_status', ProductStatus::Featured->value)
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->whereHas('stockVariant')
            ->with(['category:id,name,slug', 'offerProduct', 'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->orderByLowestPrice()
                    ->with(['price']);
            }])
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->limit(8)
            ->get();

        return successResponse(ProductResource::collection($featuredProducts));
    }

    public function newArrivalProduct()
    {
        $newArrivalProducts = Product::query()
            ->select('id', 'slug', 'product_name', 'headline', 'product_image', 'category_id', 'has_official_warranty', 'is_chinese', 'is_unofficial', 'is_refurbished', 'purchase_point', 'booking_amount')
            ->where('status', ProductStatus::Active->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->whereHas('stockVariant')
            ->with(['category:id,name,slug', 'offerProduct', 'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->orderByLowestPrice()
                    ->with(['price']);
            }])
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        return successResponse(ProductResource::collection($newArrivalProducts));
    }

    public function newArrival(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 12), 48);

        $products = Product::query()
            ->select(
                'id',
                'slug',
                'product_image',
                'product_name',
                'category_id',
                'brand_id',
                'status',
                'a_status',
                'headline',
                'created_at',
                'has_official_warranty',
                'is_chinese',
                'is_unofficial',
                'is_refurbished',
                'purchase_point',
                'booking_amount'
            )
            ->where('status', ProductStatus::Active->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->whereHas('stockVariant')
            ->with(['category:id,name,slug', 'offerProduct', 'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->orderByLowestPrice()
                    ->with(['price']);
            }])
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return successResponse(new ShopProductResourceCollection($products));
    }

    public function news()
    {
        $news = News::query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('status', StatusEnum::Active)
            ->select(['id', 'heading', 'name', 'slug', 'image', 'description', 'created_at'])
            ->orderByDesc('id')
            ->get();

        return successResponse([
            'news' => NewsResource::collection($news),
            'seoData' => $this->getPageWithSeo('news'),
        ]);
    }

    public function blog(Request $request)
    {
        $blogs = News::query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('status', StatusEnum::Active)
            ->select(['id', 'heading', 'name', 'slug', 'image', 'description', 'created_at'])
            ->orderByDesc('id')
            ->paginate(clampPerPage($request->input('per_page'), 12, 48));

        return successResponse([
            'news' => NewsResource::collection($blogs),
            'pagination' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ],
            'seoData' => $this->getPageWithSeo('blog'),
        ]);
    }

    /**
     * Join two-word spellings customers type ("i phone") into the one-word
     * product name ("iphone") so the search engine can match it exactly.
     */
    private function normalizeSearchQuery(string $query): string
    {
        $query = mb_scrub($query, 'UTF-8');

        $normalized = preg_replace(
            [
                '/\bi\s+(phone|pad|mac|pod)(s?)\b/iu',
                '/\bair\s+(pods?|tag)\b/iu',
                '/\bmac\s+book\b/iu',
            ],
            ['i$1$2', 'air$1', 'macbook'],
            $query,
        );

        return trim($normalized ?? $query);
    }

    public function searchProduct(Request $request)
    {
        $query = $this->normalizeSearchQuery($request->input('query', ''));

        if (strlen($query) < 2) {
            return successResponse(['products' => []]);
        }

        $result = Product::search($query, function ($meilisearch, string $query, array $options) {
            $options['attributesToSearchOn'] = [
                'name',
                'name_words',
                'headline',
                'short_description',
                'category',
                'brand',
            ];
            $options['matchingStrategy'] = 'last';

            return $meilisearch->search($query, $options);
        })
            ->query(function ($builder) {
                $builder->with([
                    'category',
                    'offerProduct',
                    'stockVariant' => function ($q) {
                        $q->where('status', '1')
                            ->with('price')
                            ->orderByLowestPrice();
                    },
                    'brand',
                ])->whereHas('brand', function ($q) {
                    $q->where('status', true);
                })->withCount('cares');
            })
            ->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->take(20)
            ->get();

        return successResponse(SearchProductResource::collection($result));
    }

    public function searchProductResult(Request $request)
    {
        $query = $this->normalizeSearchQuery($request->input('query', ''));

        if (strlen($query) < 2) {
            return successResponse([
                'products' => [],
                'seoData' => null,
            ]);
        }

        $products = Product::search($query)
            ->query(
                fn ($q) => $q->with(['category:id,name,slug', 'offerProduct', 'stockVariant' => function ($q) {
                    $q->where('status', '1')
                        ->with(['price']);
                }])
                    ->withCount(['reviews', 'cares'])
                    ->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
                    ->whereHas('brand', function ($q) {
                        $q->where('status', true);
                    })
            )
            ->paginate(clampPerPage(request('per_page'), 12, 48));

        return successResponse(new ShopProductResourceCollection($products));
    }

    public function reelsVideo()
    {
        $reels = ReelsVideo::query()
            ->whereHas('product', fn ($query) => $query->where('status', ProductStatus::Active->value))
            ->with(['product:id,slug,product_name,headline,product_image,short_description,brand_id', 'product.brand', 'product.stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->with(['price']);
            }])
            ->where('status', StatusEnum::Active)
            ->select(['id', 'product_id', 'video_link'])
            ->get();

        return successResponse(ReelsVideoResource::collection($reels));
    }

    public function homePreOwnedProduct()
    {
        $preOwneds = PreOwned::query()
            ->with(['product' => function ($q) {
                $q->select(['id', 'product_name', 'slug', 'brand_id', 'category_id'])
                    ->with(['brand:id,name,slug', 'category:id,name,slug']);
            },   'attributes' => function ($q) {
                $q->with('attribute', 'attributeValue');
            }])
            ->where('is_sold', '!=', true)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return successResponse(PreOwnedResource::collection($preOwneds));
    }
}
