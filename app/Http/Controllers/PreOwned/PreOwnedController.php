<?php

namespace App\Http\Controllers\PreOwned;

use App\Filters\Product\UsedProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesProductCoupon;
use App\Http\Resources\PreOwned\PreOwnedResourceCollection;
use App\Models\Brand\Brand;
use App\Models\Page\Page;
use App\Models\PreOwned\PreOwned;
use App\Models\PreOwned\PreOwnedProductSeo;
use App\Models\Product\Product;
use App\Models\Seo\Seo;
use App\Support\PriceCalculator;
use Illuminate\Http\Request;

class PreOwnedController extends Controller
{
    use ResolvesProductCoupon;

    public function index(Request $request)
    {
        $brand = $request->filled('brand');
        $category = $request->filled('category');
        $search = $request->filled('search');
        $stock = $request->get('stock');
        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        $sortBy = $request->get('sort_by');

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

        $preOwneds = Product::query()
            ->when($brand, function ($query) use ($request) {
                $query->whereHas('brand', function ($q) use ($request) {
                    $q->where('slug', $request->brand);
                });
            })
            ->when($category, function ($query) use ($request) {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            })
            ->when($search, function ($query) use ($request) {
                $query->whereRaw('LOWER(product_name) LIKE ?', ['%'.strtolower($request->search).'%']);
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
            ->paginate(clampPerPage(request('per_page'), 12, 48));

        return successResponse(PreOwnedResourceCollection::make($preOwneds));
    }

    public function preOwnedProduct(string $slug, Request $request)
    {
        $preOwnedSingle = PreOwned::query()
            ->whereHas('product', function ($q) use ($slug) {
                $q->where('slug', $slug);
            })
            ->with(['product' => function ($q) {
                $q->select(['id', 'product_name', 'slug', 'brand_id'])
                    ->with(['brand:id,name,shop_banner']);
            }])
            ->first();

        if (! $preOwnedSingle) {
            return responseMessage(message: 'Product not found', code: 404);
        }

        $query = PreOwned::query()
            ->whereHas('product', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });

        // Apply Filter
        (new UsedProductFilter([
            'condition' => request('condition'),
            'storage' => request('storage'),
            'stock' => request('stock'),
            'min_price' => request('min_price'),
            'max_price' => request('max_price'),
            'sort_by' => request('sort_by'),
        ]))->apply($query);

        $paginated = $query
            ->with([
                'product' => function ($q) {
                    $q->select(['id', 'product_name', 'headline', 'slug', 'brand_id', 'category_id', 'short_description'])
                        ->with([
                            'specificationItems.parent',
                            'specificationItems.sub',
                        ]);
                },
                'images',
                'attributes' => function ($q) {
                    $q->with('attribute', 'attributeValue');
                },
            ])
            ->paginate(clampPerPage($request->input('per_page'), 12, 48));

        $preOwneds = $paginated->map(fn ($preOwned) => $this->formatPreOwnedCard($preOwned));

        return successResponse([
            'preOwneds' => $preOwneds,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'brand' => $preOwnedSingle->product?->brand,
            'seoData' => $this->seoData($preOwnedSingle),
        ]);
    }

    // Brands that actually have at least one available (not sold) pre-owned
    // unit — for the marketplace filter sidebar. Not every site brand sells
    // used devices, so this is deliberately narrower than the general
    // brands/all-brands endpoints.
    public function brands()
    {
        $brands = Brand::query()
            ->select(['id', 'name', 'slug'])
            ->whereHas('products.preOwned', fn ($q) => $q->where('is_sold', '!=', true))
            ->orderBy('name')
            ->get();

        return successResponse($brands);
    }

    /**
     * Home/landing style listing: one "shelf" per product model (e.g. iPhone 13 Pro Max,
     * iPhone 14), each shelf showing a handful of its individual pre-owned units (each
     * with its own condition/battery health/price), plus a total count so the frontend
     * can show a "See All" link to `pre-owneds/{slug}/products` for that model.
     */
    public function grouped(Request $request)
    {
        $perGroupLimit = max(1, (int) $request->input('limit', 4));
        $brandSlugs = array_filter(explode(',', (string) $request->input('brand', '')));

        $products = Product::query()
            ->select(['id', 'product_name', 'slug', 'brand_id'])
            ->whereHas('preOwned', fn ($q) => $q->where('is_sold', '!=', true))
            ->when(count($brandSlugs) > 0, function ($query) use ($brandSlugs) {
                $query->whereHas('brand', fn ($q) => $q->whereIn('slug', $brandSlugs));
            })
            ->withCount(['preOwned as available_pre_owned_count' => fn ($q) => $q->where('is_sold', '!=', true)])
            ->orderByRaw("CASE WHEN (SELECT slug FROM brands WHERE brands.id = products.brand_id) = 'apple' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('id')
            ->paginate(clampPerPage($request->input('per_page'), 6, 48));

        // A single query for all units across this page's products, grouped in memory
        // per product — eager-loading a relation with a ->limit() only limits the whole
        // query, not per parent, so per-shelf limiting has to happen after fetching.
        $unitsByProduct = PreOwned::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->where('is_sold', '!=', true)
            ->with([
                'attributes.attribute',
                'attributes.attributeValue',
                'product:id,product_name,headline,slug',
            ])
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id');

        $groups = $products->map(function ($product) use ($unitsByProduct, $perGroupLimit) {
            $units = $unitsByProduct->get($product->id, collect())->take($perGroupLimit);

            return [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->product_name,
                    'slug' => $product->slug,
                ],
                'total_available' => $product->available_pre_owned_count,
                'preOwneds' => $units->map(fn ($preOwned) => $this->formatPreOwnedCard($preOwned))->values(),
            ];
        })->values();

        return successResponse([
            'groups' => $groups,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'seoData' => $this->pageSeoData('marketplace'),
        ]);
    }

    // Same lookup SeoController::seo() does for a static page's SEO record,
    // returned as a plain array here (null if missing) instead of its own
    // HTTP response, so it can ride along on an existing page's data fetch
    // instead of needing a second request.
    private function pageSeoData(string $pageSlug): ?array
    {
        $page = Page::query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', $pageSlug)
            ->first();

        if (! $page) {
            return null;
        }

        $seo = Seo::query()
            ->where('tenant_id', $page->tenant_id)
            ->where('model_id', $page->id)
            ->where('model_type', 'App\Models\Pos\Page\Page')
            ->first();

        if (! $seo) {
            return null;
        }

        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'page_description' => $page->description ?? null,
            'tags' => $seo->tags,
            'siteName' => config('app.name'),
            'ogImage' => $page->primary_logo ?? null,
            'banner' => $page->shop_banner ?? null,
            'info_banner' => $page->info_banner ?? null,
            'canonicalUrl' => config('services.front_base_url'),
        ];
    }

    private function formatPreOwnedCard(PreOwned $preOwned): array
    {
        $discountPercent = $preOwned->discount_type === 'percent' ? (float) ($preOwned->discount_amount ?? 0) : 0;
        $discountFixed = $preOwned->discount_type === 'fixed' ? (float) ($preOwned->discount_amount ?? 0) : 0;
        $regularPrice = (float) ($preOwned->price ?? 0);

        return [
            'id' => $preOwned->id,
            'product_id' => $preOwned->product_id,
            'product_variant_id' => $preOwned->product_variant_id,
            'product_name' => $preOwned->product?->product_name,
            'headline' => $preOwned->product?->headline,
            'slug' => $preOwned->product?->slug,
            'product_image' => $preOwned->thumbnail_image,
            'battery_health' => $preOwned->battery_health,
            'description' => $preOwned->description,
            'short_description' => $preOwned->product?->short_description,
            'regular_price' => $regularPrice,
            'final_price' => PriceCalculator::finalPrice($regularPrice, $discountPercent, $discountFixed),
            'discount_amount' => PriceCalculator::discountAmount($regularPrice, $discountPercent, $discountFixed),
            'discount_label' => PriceCalculator::discountLabel($discountPercent, $discountFixed),
            'is_box' => $preOwned->box,
            'is_stock' => $preOwned->is_sold ? false : true,
            'charging_time' => $preOwned->charging_time,
            'official_service_warranty' => $preOwned->official_service_warranty,

            'attributes' => $preOwned->attributes?->map(fn ($attribute) => [
                'name' => $attribute->attribute?->name,
                'value' => $attribute->attributeValue?->value,
            ]) ?? [],

        ];
    }

    private function seoData($preOwnedSingle)
    {
        $seo = PreOwnedProductSeo::query()
            ->where('product_id', $preOwnedSingle->product_id)
            ->first();

        $base = [
            'title' => $preOwnedSingle->product->product_name,
            'canonical' => config('services.front_base_url').'/used-device/'.$preOwnedSingle->product->slug,
            'ogImage' => $preOwnedSingle?->thumbnail_image ?? null,
            'siteName' => config('app.name'),
        ];

        if (! $seo) {
            return array_merge($base, [
                'description' => '',
                'tags' => '',
                'longDescription' => '',
            ]);
        }

        return array_merge($base, [
            'title' => $seo->title ?? $preOwnedSingle->product->product_name,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'long_description' => $seo->long_description,
        ]);
    }

    private function formattedSpecifications(object $specificationItems): array
    {
        if (! $specificationItems) {
            return [];
        }

        return $specificationItems
            ->groupBy(fn ($item) => optional($item->parent)->id)
            ->map(function ($items) {
                $group = $items->first()->parent;

                return [
                    'id' => $group?->id,
                    'name' => $group?->name,
                    'group_values' => $items->map(fn ($item) => [
                        'id' => $item->id,
                        'name' => $item->sub?->name,
                        'details' => $item->details,
                    ])->values(),
                ];
            })
            ->values()
            ->toArray();
    }
}
