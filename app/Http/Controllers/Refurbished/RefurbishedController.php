<?php

namespace App\Http\Controllers\Refurbished;

use App\Enums\ProductStatus;
use App\Filters\Product\ProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\Shop\ShopProductResourceCollection;
use App\Models\Brand\Brand;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Http\Request;

class RefurbishedController extends Controller
{
    public function index(Request $request)
    {
        $isPriceSort = in_array($request->input('sort_by'), ['price_asc', 'price_desc']);


        $query = Product::query()
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
            ->withCount(['giftProduct', 'cares'])
            ->withAvg('reviews', 'rating');

        (new ProductFilter([
            'brand'     => $request->input('brand'),
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

        $products = $query->paginate(request()->input('per_page', 12));

        return successResponse(new ShopProductResourceCollection($products));
    }

    public function brand()
    {
        $brandIds = Product::query()
            ->select('brand_id')
            ->where('status', ProductStatus::Active->value)
            ->where('is_refurbished', true)
            ->whereHas('brand', function ($query) {
                $query->where('status', true);
            })
            ->distinct()
            ->pluck('brand_id');

        $brands = Brand::query()
            ->whereIn('id', $brandIds)
            ->where('status', true)
            ->select(['name', 'slug'])
            ->orderBy('name')
            ->get();

        return successResponse($brands);
    }
}
