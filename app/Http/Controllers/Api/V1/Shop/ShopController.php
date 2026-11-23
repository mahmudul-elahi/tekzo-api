<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Enums\ProductStatus;
use App\Support\Filters\Product\ProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\Shop\ShopProductResourceCollection;
use App\Models\Page\Page;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Models\Seo\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int)$request->input('per_page', 12), 48);
        $currentPage = $request->input('page', 1);
        $isPriceSort = in_array($request->input('sort_by'), ['price_asc', 'price_desc']);

        $query = Product::query()
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
                    ->limit(1)
            ])
            ->whereIn('products.status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->orderByRaw('CASE WHEN has_stock > 0 THEN 0 ELSE 1 END ASC')
            // A price sort's own ordering has to be the next criterion right after
            // stock — numbering/id here would otherwise decide almost every row before
            // price ever got a chance to (orderBy calls are additive, not replacing).
            ->when(!$isPriceSort, function ($query) {
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

        (new ProductFilter([
            'brand'     => $request->input('brand'),
            'category'  => $request->input('category'),
            'stock'     => $request->input('stock'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'sort_by'   => $request->input('sort_by'),
        ]))->apply($query);

        if ($isPriceSort) {
            // Products can tie on price — without a unique final tiebreaker, ties have
            // no guaranteed order across separate paginated requests, which can
            // duplicate or skip a product between pages.
            $query->orderBy('products.id', 'asc');
        }

        $shops = $query->paginate($perPage, ['products.*'], 'page', $currentPage);

        return successResponse([
            'products' => new ShopProductResourceCollection($shops),
            'seoData' => $this->getPageWithSeo('shop'),
        ]);
    }


    protected function getPageWithSeo(string $slug)
    {
        $page = Page::query()
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', $slug)
            ->select(['id', 'title', 'slug', 'description', 'tags', 'tenant_id', 'primary_logo', 'shop_banner', 'info_banner'])
            ->first();

        if (!$page) {
            return null;
        }


        $seo = Seo::query()
            ->where('model_id', $page->id)
            ->where('model_type', 'App\Models\Pos\Page\Page')
            ->where('tenant_id', $page->tenant_id)
            ->first();

        if (!$seo) {
            return null;
        }

        return [
            'title' => $seo->title ?? $page->title,
            'description' => $seo->description,
            'page_description' => $page->description ?? null,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url') . '/shop',
            'ogImage' => $page->primary_logo,
            'banner' => $page->shop_banner,
            'info_banner' => $page->info_banner,
            'siteName' => config('app.name'),
        ];
    }

    public function product()
    {

        $products = Product::query()
            ->whereIn('status', [ProductStatus::Active->value, ProductStatus::Comming->value])
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->whereHas('stockVariant')
            ->select(['slug', 'updated_at'])
            ->orderByDesc('id')
            ->get();

        return successResponse($products);
    }
}
