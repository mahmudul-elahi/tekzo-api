<?php

namespace App\Repositories\Product;

use App\Enums\ProductStatus;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Repositories\BaseRepository;
use App\Support\Filters\Product\ProductFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $product)
    {
        $this->model = $product;
    }

    /**
     * A visible (active or coming soon) product with everything the detail page needs.
     */
    public function findVisibleBySlugForDetail(string $slug): ?Product
    {
        return $this->query()
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
    }

    public function findIdBySlug(string $slug): ?Product
    {
        return $this->query()
            ->select('id')
            ->whereSlug($slug)
            ->first();
    }

    public function findCategorizedBySlug(string $slug): ?Product
    {
        return $this->query()
            ->whereNotNull('category_id')
            ->whereSlug($slug)
            ->first();
    }

    /**
     * Random sellable products from the same category as the given product.
     *
     * @return Collection<int, Product>
     */
    public function randomSellableInCategory(Product $product, int $limit): Collection
    {
        return $this->applySellableConstraints(
            $this->query()
                ->with($this->cardRelations())
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
        )
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    /**
     * Random sellable products from any category, skipping the given ids.
     *
     * @param  array<int, int>  $excludeIds
     * @return Collection<int, Product>
     */
    public function randomSellableExcept(array $excludeIds, int $limit): Collection
    {
        return $this->applySellableConstraints(
            $this->query()
                ->with($this->cardRelations())
                ->whereNotIn('id', $excludeIds)
        )
            ->withCount('giftProduct')
            ->withAvg('reviews', 'rating')
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    /**
     * Visible products in the given categories, optionally narrowed to one brand, with listing filters applied.
     *
     * @param  array<int, int>  $categoryIds
     * @param  array{stock: mixed, min_price: mixed, max_price: mixed}  $filters
     */
    public function visibleInCategoriesQuery(array $categoryIds, ?string $brandSlug, array $filters): Builder
    {
        $query = $this->query()
            ->whereIn('category_id', $categoryIds)
            ->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->when($brandSlug !== null, function ($query) use ($brandSlug) {
                $query->whereHas('brand', function ($q) use ($brandSlug) {
                    $q->where('slug', $brandSlug);
                });
            });

        (new ProductFilter($filters))->apply($query);

        return $query;
    }

    /**
     * Paginate a category listing: in-stock first, then by display price, numbering and id.
     */
    public function paginateCategoryListing(Builder $baseQuery, ?string $sortBy, int $perPage): LengthAwarePaginator
    {
        return (clone $baseQuery)
            ->addSelect($this->listingPriceSelects())
            ->orderByRaw('has_stock DESC')
            ->orderByRaw('display_price IS NULL ASC')
            ->orderBy('display_price', $sortBy === 'price_asc' ? 'asc' : 'desc')
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
            ->paginate($perPage);
    }

    /**
     * Paginate a brand's visible products: in-stock first, then by display price, numbering and id.
     *
     * @param  array{stock: mixed, min_price: mixed, max_price: mixed}  $filters
     */
    public function paginateBrandListing(int $brandId, array $filters, ?string $categorySlug, ?string $sortBy, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->select('products.*')
            ->where('brand_id', $brandId)
            ->tap(function ($query) use ($filters) {
                (new ProductFilter($filters))->apply($query);
            })
            ->addSelect($this->listingPriceSelects())
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->with('category:id,name,slug')
            ->whereIn('products.status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->orderByRaw('has_stock DESC')
            ->orderByRaw('display_price IS NULL ASC')
            ->orderBy('display_price', $sortBy === 'price_asc' ? 'asc' : 'desc')
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
            ->when($categorySlug !== null, function ($query) use ($categorySlug) {
                $query->whereHas('category', function ($q) use ($categorySlug) {
                    $q->where('slug', $categorySlug);
                });
            })
            ->paginate($perPage);
    }

    /**
     * The first 4 active products (home "flash sale products" block).
     *
     * @return Collection<int, Product>
     */
    public function firstActiveForFlashSaleBlock(): Collection
    {
        return $this->query()
            ->select('id', 'product_name', 'slug', 'headline', 'product_image', 'category_id', 'is_refurbished')
            ->where('status', ProductStatus::Active->value)

            ->with(['category:id,name,slug', 'stockVariant.price'])
            ->withCount('reviews')
            ->limit(4)
            ->get();
    }

    /**
     * Active products in an offer, in-stock first, then by numbering.
     *
     * @return Collection<int, Product>
     */
    public function activeInOffer(int $offerId): Collection
    {
        return $this->query()
            ->whereHas('offerProduct', function ($query) use ($offerId) {
                $query->where('offer_id', $offerId);
            })
            ->where('status', ProductStatus::Active->value)
            ->addSelect([
                'has_stock' => ProductVariant::selectRaw('MAX(stock)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', '1')
                    ->limit(1),
            ])
            ->with(['category:id,name,slug', 'offerProduct' => function ($query) use ($offerId) {
                $query->where('offer_id', $offerId);
            }, 'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->with(['price']);
            }])

            ->withCount(['offerProduct', 'cares'])
            ->withAvg('reviews', 'rating')
            ->orderByRaw('has_stock DESC')
            ->orderBy('numbering', 'asc')
            ->get();
    }

    /**
     * Up to 8 active featured products from active brands that have a stock variant.
     *
     * @return Collection<int, Product>
     */
    public function featured(): Collection
    {
        return $this->query()
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
    }

    /**
     * The 4 newest arrivals (created in the last 30 days).
     *
     * @return Collection<int, Product>
     */
    public function latestArrivals(): Collection
    {
        return $this->newArrivalsQuery(['id', 'slug', 'product_name', 'headline', 'product_image', 'category_id', 'has_official_warranty', 'is_chinese', 'is_unofficial', 'is_refurbished', 'purchase_point', 'booking_amount'])
            ->limit(4)
            ->get();
    }

    /**
     * Paginated arrivals created in the last 30 days, newest first.
     */
    public function paginateNewArrivals(int $perPage): LengthAwarePaginator
    {
        return $this->newArrivalsQuery([
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
            'booking_amount',
        ])
            ->paginate($perPage);
    }

    /**
     * Up to 20 search-as-you-type matches from the search index.
     *
     * @return Collection<int, Product>
     */
    public function searchSuggestions(string $query): Collection
    {
        return Product::search($query, function ($meilisearch, string $query, array $options) {
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
    }

    /**
     * Paginated full search results from the search index.
     */
    public function paginateSearch(string $query, int $perPage): LengthAwarePaginator
    {
        return Product::search($query)
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
            ->paginate($perPage);
    }

    /**
     * The shop listing: visible products from active brands, in stock first.
     * Without a price sort, then by numbering and newest; with one, the filter's
     * price ordering comes next and the id is the final tiebreaker.
     *
     * @param  array{brand: mixed, category: mixed, stock: mixed, min_price: mixed, max_price: mixed, sort_by: mixed}  $filters
     */
    public function paginateShop(array $filters, int $perPage, mixed $page): LengthAwarePaginator
    {
        $isPriceSort = in_array($filters['sort_by'], ['price_asc', 'price_desc']);

        $query = $this->query()
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
                'numbering',
                'has_official_warranty',
                'is_chinese',
                'is_unofficial',
                'is_refurbished',
                'purchase_point',
                'booking_amount'

            )
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->addSelect([
                'has_stock' => ProductVariant::selectRaw('MAX(stock)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', '1')
                    ->limit(1),
            ])
            ->whereIn('products.status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->orderByRaw('CASE WHEN has_stock > 0 THEN 0 ELSE 1 END ASC')
            // A price sort's own ordering has to be the next criterion right after
            // stock — numbering/id here would otherwise decide almost every row before
            // price ever got a chance to (orderBy calls are additive, not replacing).
            ->when(! $isPriceSort, function ($query) {
                $query->orderBy('numbering', 'asc')->orderByDesc('id');
            })
            ->with([
                'category:id,name,slug',
                'offerProduct',
                'stockVariant' => function ($query) {
                    $query->where('status', '1')
                        ->orderByLowestPrice()
                        ->with(['price']);
                },
            ])
            ->withCount(['offerProduct', 'cares'])
            ->withAvg('reviews', 'rating');

        (new ProductFilter($filters))->apply($query);

        if ($isPriceSort) {
            // Products can tie on price — without a unique final tiebreaker, ties have
            // no guaranteed order across separate paginated requests, which can
            // duplicate or skip a product between pages.
            $query->orderBy('products.id', 'asc');
        }

        return $query->paginate($perPage, ['products.*'], 'page', $page);
    }

    /**
     * Slug and updated_at of every visible product with a stock variant, newest first (sitemap).
     *
     * @return Collection<int, Product>
     */
    public function sitemapEntries(): Collection
    {
        return $this->query()
            ->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->whereHas('stockVariant')
            ->select(['slug', 'updated_at'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Active refurbished products from active brands with a stock variant, in stock first.
     * Sorting follows the same rules as the shop listing.
     *
     * @param  array{brand: mixed, stock: mixed, min_price: mixed, max_price: mixed, sort_by: mixed}  $filters
     */
    public function paginateRefurbished(array $filters, int $perPage): LengthAwarePaginator
    {
        $isPriceSort = in_array($filters['sort_by'], ['price_asc', 'price_desc']);

        $query = $this->query()
            ->select('id', 'slug', 'product_name', 'headline', 'product_image', 'category_id', 'has_official_warranty', 'is_chinese', 'is_unofficial', 'is_refurbished', 'purchase_point', 'booking_amount')
            ->where('status', ProductStatus::Active->value)
            ->where('is_refurbished', true)
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->whereHas('stockVariant')
            ->with(['category:id,name,slug', 'offerProduct', 'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->orderByLowestPrice()
                    ->with(['price']);
            }])
            ->addSelect([
                'has_stock' => ProductVariant::selectRaw('MAX(stock)')
                    ->whereColumn('product_id', 'products.id')
                    ->where('status', '1')
                    ->limit(1),
            ])
            ->whereIn('products.status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->orderByRaw('CASE WHEN has_stock > 0 THEN 0 ELSE 1 END ASC')
            ->when(! $isPriceSort, function ($query) {
                $query->orderBy('numbering', 'asc')->orderByDesc('id');
            })
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating');

        (new ProductFilter($filters))->apply($query);

        if ($isPriceSort) {
            $query->orderBy('products.id', 'asc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Distinct brand ids of active refurbished products from active brands.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function refurbishedBrandIds(): \Illuminate\Support\Collection
    {
        return $this->query()
            ->select('brand_id')
            ->where('status', ProductStatus::Active->value)
            ->where('is_refurbished', true)
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->distinct()
            ->pluck('brand_id');
    }

    /**
     * Active products in an offer, with only that offer's offerProduct loaded.
     */
    public function paginateActiveInOffer(int $offerId, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->whereHas('offerProduct', function ($query) use ($offerId) {
                $query->where('offer_id', $offerId);
            })
            ->with([
                'brand:id,name,slug',
                'stockVariant' => function ($query) {
                    $query->where('status', '1')
                        ->orderByLowestPrice()
                        ->with('price');
                },
                'offerProduct' => function ($query) use ($offerId) {
                    $query->where('offer_id', $offerId);
                },
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('cares')
            ->where('status', ProductStatus::Active->value)
            ->paginate($perPage);
    }

    /**
     * Products that have pre-owned units matching the stock/price filters, each with those
     * units, their count and price range. Apple first when no sort is given.
     *
     * @param  array{brand: ?string, category: ?string, search: ?string, stock: mixed, min_price: mixed, max_price: mixed, sort_by: mixed}  $filters
     */
    public function paginateWithPreOwnedUnits(array $filters, int $perPage): LengthAwarePaginator
    {
        $stock = $filters['stock'];
        $minPrice = $filters['min_price'];
        $maxPrice = $filters['max_price'];
        $sortBy = $filters['sort_by'];

        $preOwnedConstraint = function ($query) use ($stock, $minPrice, $maxPrice) {
            if ($stock === 'in_stock') {
                $query->where('is_sold', false);
            } elseif ($stock === 'out_of_stock') {
                $query->where('is_sold', true);
            }

            if ($minPrice !== null && $minPrice !== '') {
                $query->where('price', '>=', $minPrice);
            }

            if ($maxPrice !== null && $maxPrice !== '') {
                $query->where('price', '<=', $maxPrice);
            }
        };

        return $this->query()
            ->when($filters['brand'] !== null, function ($query) use ($filters) {
                $query->whereHas('brand', function ($q) use ($filters) {
                    $q->where('slug', $filters['brand']);
                });
            })
            ->when($filters['category'] !== null, function ($query) use ($filters) {
                $query->whereHas('category', function ($q) use ($filters) {
                    $q->where('slug', $filters['category']);
                });
            })
            ->when($filters['search'] !== null, function ($query) use ($filters) {
                $query->whereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($filters['search']).'%']);
            })
            ->select(['id', 'product_name', 'slug', 'headline', 'category_id', 'brand_id'])
            ->whereHas('preOwned', $preOwnedConstraint)
            ->withCount(['preOwned as total_pre_owned' => $preOwnedConstraint])
            ->withMin(['preOwned' => $preOwnedConstraint], 'price')
            ->withMax(['preOwned' => $preOwnedConstraint], 'price')
            ->with(['preOwned' => $preOwnedConstraint, 'category:id,name,slug', 'brand:id,name,slug'])
            ->when($sortBy === 'price_asc', fn ($query) => $query->orderBy('pre_owned_min_price', 'asc')->orderBy('id', 'asc'))
            ->when($sortBy === 'price_desc', fn ($query) => $query->orderBy('pre_owned_min_price', 'desc')->orderBy('id', 'asc'))
            ->when($sortBy === 'newest', fn ($query) => $query->orderByDesc('id'))
            ->when(! $sortBy, function ($query) {
                $query->orderByRaw("CASE WHEN (SELECT slug FROM brands WHERE brands.id = products.brand_id) = 'apple' THEN 0 ELSE 1 END ASC")
                    ->orderByDesc('id');
            })
            ->paginate($perPage);
    }

    /**
     * Products with at least one unsold pre-owned unit (optionally from the given brands),
     * with that count. Apple first, then newest.
     *
     * @param  array<int, string>  $brandSlugs
     */
    public function paginateWithAvailablePreOwned(array $brandSlugs, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->select(['id', 'product_name', 'slug', 'brand_id'])
            ->whereHas('preOwned', fn ($q) => $q->where('is_sold', '!=', true))
            ->when(count($brandSlugs) > 0, function ($query) use ($brandSlugs) {
                $query->whereHas('brand', fn ($q) => $q->whereIn('slug', $brandSlugs));
            })
            ->withCount(['preOwned as available_pre_owned_count' => fn ($q) => $q->where('is_sold', '!=', true)])
            ->orderByRaw("CASE WHEN (SELECT slug FROM brands WHERE brands.id = products.brand_id) = 'apple' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Record a unique view; repeat views from the same visitor are ignored.
     */
    public function recordView(int $productId, string $visitorHash): void
    {
        DB::table('product_views')->insertOrIgnore([
            'product_id' => $productId,
            'visitor_hash' => $visitorHash,
            'viewed_at' => now(),
        ]);
    }

    /**
     * Active products from active brands, created in the last 30 days, with a stock variant; newest first.
     *
     * @param  array<int, string>  $columns  selected before the count/avg sub-selects are added
     */
    private function newArrivalsQuery(array $columns): Builder
    {
        return $this->query()
            ->select($columns)
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
            ->orderByDesc('created_at');
    }

    /**
     * Active product, active brand, and at least one in-stock active variant with a real price.
     */
    private function applySellableConstraints(Builder $query): Builder
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

    /**
     * Relations a product card needs.
     *
     * @return array<int|string, mixed>
     */
    private function cardRelations(): array
    {
        return [
            'category:id,name,slug',
            'offerProduct',
            'stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->where('stock', true)
                    ->orderByLowestPrice()
                    ->with(['price']);
            },
        ];
    }

    /**
     * Sub-selects used to sort listings.
     *
     * - has_stock: 1 when any active variant is in stock.
     * - display_price: the lowest final (post-discount) price across active variants,
     *   matching the card price from orderByLowestPrice()/stockVariant. Variants with a
     *   placeholder max_retail_price of 0 are skipped, so products with no valid price
     *   get NULL and always sort last ("display_price IS NULL ASC").
     *
     * @return array<string, Builder>
     */
    private function listingPriceSelects(): array
    {
        return [
            'has_stock' => ProductVariant::selectRaw('IF(MAX(stock) > 0, 1, 0)')
                ->whereColumn('product_id', 'products.id')
                ->where('status', '1')
                ->limit(1),
            'display_price' => ProductVariant::query()
                ->join('variant_prices', 'variant_prices.product_variant_id', '=', 'product_variants.id')
                ->whereColumn('product_variants.product_id', 'products.id')
                ->where('product_variants.status', '1')
                ->where('variant_prices.max_retail_price', '>', 0)
                ->selectRaw('MIN(
                        CASE
                            WHEN variant_prices.discount_percent > 0 THEN variant_prices.max_retail_price - (variant_prices.max_retail_price * variant_prices.discount_percent / 100)
                            WHEN variant_prices.discount_fixed > 0   THEN variant_prices.max_retail_price - variant_prices.discount_fixed
                            ELSE variant_prices.max_retail_price
                        END
                    )'),
        ];
    }
}
