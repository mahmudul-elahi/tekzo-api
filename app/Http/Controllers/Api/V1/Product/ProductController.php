<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Enums\ProductStatus;
use App\Enums\StatusEnum;
use App\Support\Filters\Product\ProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\PreOwned\PreOwnedResource;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\Product\ProductReviewCollection;
use App\Http\Resources\Product\SingleBrandProductResource;
use App\Http\Resources\Product\SingleCategoryProductResource;
use App\Http\Resources\Product\SinglePreOwnedProductResource;
use App\Http\Resources\Product\SingleProductResource;
use App\Models\Brand\Brand;
use App\Models\Category\Category;
use App\Models\PreOwned\PreOwned;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Models\Product\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function detail(string $slug)
    {
        $preOwnedId = request('preOwnedId');
        if ($preOwnedId) {
            $checkPreOwned = PreOwned::query()
                ->where('id', $preOwnedId)
                ->exists();

            if (! $checkPreOwned) {
                return errorResponse([], 404);
            }

            $preOwned = PreOwned::query()
                ->where('id', $preOwnedId)
                ->with([
                    'images',
                    'cares.care',
                    'gift.variants.product',
                    'attributes' => function ($q) {
                        $q->with(['attribute', 'attributeValue']);
                    },
                    'product' => function ($q) {
                        $q->with([
                            'category:id,parent_id,name,slug',
                            'brand:id,name,slug,primary_logo,secondary_logo,shop_banner',
                            'specificationItems.parent',
                            'specificationItems.sub',
                            'preOwnedSeo',
                        ])->withCount('productViews as view_count')
                            ->withCount('reviews');
                    },
                ])
                ->first();

            if (! $preOwned) {
                return errorResponse([], 404);
            }

            if ($preOwned->product) {
                $this->incrementViewCount($preOwned->product->id);
            }
            // View count increment

            return successResponse(SinglePreOwnedProductResource::make($preOwned));
        } else {
            $checkProduct = Product::query()
                ->whereSlug($slug)
                ->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
                ->whereHas('brand', function ($query) {
                    $query->where('status', true);
                })->first();

            if (! $checkProduct) {
                return errorResponse([], 404);
            }

            $product = Product::query()
                ->whereSlug($slug)
                ->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
                ->whereHas('brand', function ($query) {
                    $query->where('status', true);
                })
                ->with([
                    'category:id,parent_id,name,slug',
                    'brand:id,name,slug,primary_logo,secondary_logo,shop_banner',
                    'specificationItems.parent',
                    'specificationItems.sub',
                    'cares.care',
                    'variants' => function ($q) {
                        $q->where('status', '1')
                            ->orderByLowestPrice()
                            ->with([
                                'attributeValues' => function ($q) {
                                    $q->orderByRaw("(
                                        SELECT CASE WHEN LOWER(a.name) = 'color' THEN 0 ELSE 1 END
                                        FROM attributes a WHERE a.id = attribute_values.attribute_id LIMIT 1
                                    )");
                                },
                                'offerProduct',
                                'price',
                            ]);
                    },
                    'images',
                    'videos' => function ($q) {
                        $q->whereNull('attribute_value_id');
                    },
                    'giftProduct' => function ($q) {
                        $q->where('is_active', ProductStatus::Active->value)
                            ->select(['id', 'product_id'])
                            ->with(['variants' => function ($q) {
                                $q->select(['id', 'product_id', 'gift_product_id', 'qty'])
                                    ->with('product:id,product_name,slug,product_image');
                            }]);
                    },

                ])
                ->withCount('productViews as view_count')
                ->withCount('reviews')
                ->withAvg('reviews', 'rating')
                ->first();

            if (! $product) {
                return errorResponse([], 404);
            }

            $resolvedCoupon = DB::table('new_coupons')
                ->join('coupon_product_scopes', 'coupon_product_scopes.new_coupon_id', '=', 'new_coupons.id')
                ->where('new_coupons.status', 1)
                ->where('new_coupons.is_public', true)
                ->where(fn ($q) => $q->whereNull('new_coupons.start_date')->orWhereDate('new_coupons.start_date', '<=', now()))
                ->where(fn ($q) => $q->whereNull('new_coupons.end_date')->orWhereDate('new_coupons.end_date', '>=', now()))
                ->where(function ($q) use ($product) {
                    $q->where(fn ($q) => $q->where('coupon_product_scopes.scope_type', 'product')->where('coupon_product_scopes.product_id', $product->id))
                        ->orWhere(fn ($q) => $q->where('coupon_product_scopes.scope_type', 'brand')->where('coupon_product_scopes.brand_id', $product->brand_id))
                        ->orWhere(fn ($q) => $q->where('coupon_product_scopes.scope_type', 'category')->where('coupon_product_scopes.category_id', $product->category_id))
                        ->orWhere('coupon_product_scopes.scope_type', 'all');
                })
                ->orderByRaw("CASE
                    WHEN coupon_product_scopes.scope_type = 'product'  THEN 1
                    WHEN coupon_product_scopes.scope_type = 'brand'    THEN 2
                    WHEN coupon_product_scopes.scope_type = 'category' THEN 3
                    WHEN coupon_product_scopes.scope_type = 'all'      THEN 4
                    ELSE 5 END ASC")
                ->select('new_coupons.code', 'new_coupons.discount_type', 'new_coupons.discount_value')
                ->first();

            $product->setAttribute('resolved_coupon', $resolvedCoupon);

            // View count increment
            $this->incrementViewCount($product->id);

            return successResponse(SingleProductResource::make($product));
        }
    }

    private function incrementViewCount(int $productId): void
    {
        $forwardedFor = request()->header('X-Forwarded-For');

        if ($forwardedFor) {
            $parts = explode(',', $forwardedFor);
            $ip = trim($parts[0]);
        } else {
            $ip = request()->ip();
        }

        $visitorHash = md5($ip.request()->userAgent());

        DB::table('product_views')->insertOrIgnore([
            'product_id' => $productId,
            'visitor_hash' => $visitorHash,
            'viewed_at' => now(),
        ]);
    }

    public function productReview($slug)
    {
        $product = Product::query()
            ->select('id')
            ->whereSlug($slug)
            ->first();

        if (! $product) {
            return errorResponse([], 404);
        }

        $productId = $product->id;

        /*
        |--------------------------------------------------------------------------
        | Rating Summary
        |--------------------------------------------------------------------------
        */

        $ratingSummary = Review::query()
            ->where('product_id', $productId)
            ->where('status', StatusEnum::Active)
            // ->where('is_admin_review', false)
            ->selectRaw('
            COUNT(*) as total_reviews,
            ROUND(AVG(rating),1) as avg_rating,
            SUM(rating = 5) as five_star,
            SUM(rating = 4) as four_star,
            SUM(rating = 3) as three_star,
            SUM(rating = 2) as two_star,
            SUM(rating = 1) as one_star
        ')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Reviews List
        |--------------------------------------------------------------------------
        */

        $reviews = Review::query()
            ->with('contact:id,name')
            ->where('product_id', $productId)
            ->where('status', StatusEnum::Active)
            ->latest('id')
            ->paginate(clampPerPage(request('per_page'), 5, 48));

        return successResponse([
            'average' => (float) ($ratingSummary->avg_rating ?? 0),

            'total' => (int) ($ratingSummary->total_reviews ?? 0),

            'stars' => [
                5 => (int) ($ratingSummary->five_star ?? 0),
                4 => (int) ($ratingSummary->four_star ?? 0),
                3 => (int) ($ratingSummary->three_star ?? 0),
                2 => (int) ($ratingSummary->two_star ?? 0),
                1 => (int) ($ratingSummary->one_star ?? 0),
            ],
            'reviews' => new ProductReviewCollection($reviews),
        ]);
    }

    public function relatedProduct(string $slug)
    {
        $product = Product::query()
            ->whereNotNull('category_id')
            ->whereSlug($slug)
            ->first();

        if (! $product) {
            return errorResponse([], 404);
        }

        $withRelations = [
            'category:id,name,slug',
            'offerProduct',
            'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->where('stock', true)
                    ->orderByLowestPrice()
                    ->with(['price']);
            },
        ];

        // Step 1: related products from the same category
        $matched = $this->applyActiveProductConstraints(
            Product::query()
                ->with($withRelations)
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
        )
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->inRandomOrder()
            ->take(4)
            ->get();

        $needed = 4 - $matched->count();

        // Step 2: if the same category doesn't have enough, fill the remaining slots from other products
        if ($needed > 0) {
            $excludeIds = $matched->pluck('id')->push($product->id)->toArray();

            $extra = $this->applyActiveProductConstraints(
                Product::query()
                    ->with($withRelations)
                    ->whereNotIn('id', $excludeIds)
            )
                ->withCount('giftProduct')
                ->withAvg('reviews', 'rating')
                ->inRandomOrder()
                ->take($needed)
                ->get();

            $relatedProducts = $matched->concat($extra);
        } else {
            $relatedProducts = $matched;
        }

        return successResponse(ProductResource::collection($relatedProducts));
    }

    private function applyActiveProductConstraints($query)
    {
        return $query
            ->where('status', ProductStatus::Active)
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->whereHas(
                'variants',
                fn ($q) => $q
                    ->where('stock', true)
                    ->where('status', '1')
                    ->whereHas('price', fn ($p) => $p->where('max_retail_price', '>', 0))
            );
    }

    // Lightweight category lookup — just what the hero banner needs (name,
    // banner) — so it can render without waiting on the full paginated
    // product list — see categoryProduct() below for that.
    public function categoryInfo(string $slug)
    {
        $category = Category::query()
            ->whereSlug($slug)
            ->select(['name', 'banner'])
            ->first();

        if (! $category) {
            return errorResponse([], 404);
        }

        return successResponse([
            'name' => $category->name,
            'banner' => $category->banner,
        ]);
    }

    public function categoryProduct(string $slug, Request $request)
    {
        $category = Category::query()
            ->whereSlug($slug)
            ->with(['longDescription:id,model_id,description', 'children.children']) // nested children load
            ->first();

        if (! $category) {
            return errorResponse([], 404);
        }

        $categoryIds = $category->getAllDescendantIds();

        $baseQuery = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->when($request->filled('brand'), function ($query) use ($request) {
                $query->whereHas('brand', function ($q) use ($request) {
                    $q->where('slug', $request->brand);
                });
            });

        (new ProductFilter([
            'stock' => $request->input('stock'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
        ]))->apply($baseQuery);

        // Brand filter list must reflect every matching product, not just the current page.
        $brandIds = (clone $baseQuery)->pluck('brand_id')->filter()->unique()->values();

        $brands = Brand::query()
            ->whereIn('id', $brandIds)
            ->where('status', StatusEnum::Active->value)
            ->select(['id', 'name', 'slug', 'primary_logo'])
            ->get();

        $products = (clone $baseQuery)
            ->addSelect([
                'has_stock' => ProductVariant::selectRaw('IF(MAX(stock) > 0, 1, 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', '1')
                    ->limit(1),
                // The lowest final (post-discount) price across the product's active
                // variants — matches what orderByLowestPrice()/stockVariant below
                // actually displays as the card price, so sort order lines up with it.
                'display_price' => ProductVariant::query()
                    ->join('variant_prices', 'variant_prices.product_variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('product_variants.status', '1')
                    // Some variants have a placeholder max_retail_price of 0 (bad/incomplete
                    // data) — without excluding them, MIN() picks the 0 and floods the
                    // ascending sort with these instead of the product's real cheapest price.
                    ->where('variant_prices.max_retail_price', '>', 0)
                    ->selectRaw('MIN(
                        CASE
                            WHEN variant_prices.discount_percent > 0 THEN variant_prices.max_retail_price - (variant_prices.max_retail_price * variant_prices.discount_percent / 100)
                            WHEN variant_prices.discount_fixed > 0   THEN variant_prices.max_retail_price - variant_prices.discount_fixed
                            ELSE variant_prices.max_retail_price
                        END
                    )'),
            ])
            ->orderByRaw('has_stock DESC')
            // Products with no valid-priced variant left (display_price is NULL after
            // the >0 filter above) always sort last, regardless of asc/desc — "IS NULL"
            // evaluates to 0/1, so ASC here puts the non-null rows first either way.
            ->orderByRaw('display_price IS NULL ASC')
            ->orderBy('display_price', $request->input('sort_by') === 'price_asc' ? 'asc' : 'desc')
            ->orderBy('numbering', 'asc')
            ->orderBy('products.id', 'asc')
            // 'brand' is read per product by SingleCategoryProductResource —
            // without it, lazy-loading is blocked outside production (500).
            ->with(['offerProduct', 'brand', 'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->orderByLowestPrice()
                    ->with(['price']);
            }])
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->paginate(clampPerPage($request->input('per_page'), 12, 48));

        $category->setRelation('products', $products);
        $category->setAttribute('available_brands', $brands);

        return successResponse(SingleCategoryProductResource::make($category));
    }

    public function brandProduct($slug, Request $request)
    {
        $brand = Brand::query()
            ->whereSlug($slug)
            ->with(['longDescription:id,model_id,description'])
            ->first();

        if (! $brand) {
            return errorResponse([], 404);
        }

        $products = Product::query()
            ->select('products.*')
            ->where('brand_id', $brand->id)
            ->tap(function ($query) use ($request) {
                (new ProductFilter([
                    'stock' => $request->input('stock'),
                    'min_price' => $request->input('min_price'),
                    'max_price' => $request->input('max_price'),
                ]))->apply($query);
            })
            ->addSelect([
                'has_stock' => ProductVariant::selectRaw('IF(MAX(stock) > 0, 1, 0)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', '1')
                    ->limit(1),
                // The lowest final (post-discount) price across the product's active
                // variants — matches what orderByLowestPrice()/stockVariant below
                // actually displays as the card price, so sort order lines up with it.
                'display_price' => ProductVariant::query()
                    ->join('variant_prices', 'variant_prices.product_variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('product_variants.status', '1')
                    // Some variants have a placeholder max_retail_price of 0 (bad/incomplete
                    // data) — without excluding them, MIN() picks the 0 and floods the
                    // ascending sort with these instead of the product's real cheapest price.
                    ->where('variant_prices.max_retail_price', '>', 0)
                    ->selectRaw('MIN(
                        CASE
                            WHEN variant_prices.discount_percent > 0 THEN variant_prices.max_retail_price - (variant_prices.max_retail_price * variant_prices.discount_percent / 100)
                            WHEN variant_prices.discount_fixed > 0   THEN variant_prices.max_retail_price - variant_prices.discount_fixed
                            ELSE variant_prices.max_retail_price
                        END
                    )'),
            ])
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->with('category:id,name,slug')
            ->whereIn('products.status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->orderByRaw('has_stock DESC')
            // Products with no valid-priced variant left (display_price is NULL after
            // the >0 filter above) always sort last, regardless of asc/desc — "IS NULL"
            // evaluates to 0/1, so ASC here puts the non-null rows first either way.
            ->orderByRaw('display_price IS NULL ASC')
            ->orderBy('display_price', $request->input('sort_by') === 'price_asc' ? 'asc' : 'desc')
            ->orderBy('numbering', 'asc')
            ->orderBy('products.id', 'asc')
            ->with([
                'offerProduct',
                'stockVariant' => function ($q) {
                    $q->where('status', '1')
                        ->orderByLowestPrice()
                        ->with(['price']);
                },
            ])
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            })
            ->paginate(clampPerPage($request->input('per_page'), 12, 48));

        $brand->setRelation('products', $products);

        return successResponse(SingleBrandProductResource::make($brand));
    }

    public function preOwnedRelatedProduct(string $slug)
    {
        $preOwnedId = request('preOwnedId');

        $checkPreOwned = PreOwned::query()
            ->where('id', $preOwnedId)
            ->exists();

        if (! $checkPreOwned) {
            return errorResponse([], 404);
        }

        $withRelations = [
            'product' => function ($q) {
                $q->select(['id', 'product_name', 'headline', 'slug', 'brand_id', 'category_id', 'short_description']);
            },
            'attributes' => function ($q) {
                $q->with('attribute', 'attributeValue');
            },
        ];

        // Step 1: Get related preowned items matching the same product slug
        $matched = PreOwned::query()
            ->where('is_sold', '!=', true)
            ->whereHas('product', function ($q) use ($slug) {
                $q->where('slug', $slug);
            })
            ->with($withRelations)
            ->where('id', '!=', $preOwnedId)
            ->inRandomOrder()
            ->take(5)
            ->get();

        $needed = 5 - $matched->count();

        // Step 2: If less than 5, fill the remaining slots from other preowned items
        if ($needed > 0) {
            $excludeIds = $matched->pluck('id')->push($preOwnedId)->toArray();

            $extra = PreOwned::query()
                ->with($withRelations)
                ->whereNotIn('id', $excludeIds)
                ->inRandomOrder()
                ->take($needed)
                ->get();

            $result = $matched->concat($extra);
        } else {
            $result = $matched;
        }

        return successResponse(PreOwnedResource::collection($result));
    }
}
