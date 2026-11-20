<?php

namespace App\Http\Controllers\Offer;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Offer\OfferResourceCollection;
use App\Models\Offer\Offer;
use App\Models\Product\Product;
use App\Support\PriceCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function index()
    {
        $offers = Offer::query()
            ->orderByDesc('id')
            ->where('is_active', 1)
            ->paginate(request('per_page', 12));

        return successResponse(new OfferResourceCollection($offers));
    }



    public function offerProduct($slug)
    {
        $offer = Offer::query()
            ->where('slug', $slug)
            ->where('is_active', 1)
            ->select('id', 'slug', 'title', 'description', 'banner', 'start_at', 'end_at')
            ->first();

        if (!$offer) {
            return responseMessage('Offer not found', 404);
        }

        $products = Product::query()
            ->whereHas('offerProduct', function ($query) use ($offer) {
                $query->where('offer_id', $offer->id);
            })
            ->with([
                'brand:id,name,slug',
                'stockVariant' => function ($query) {
                    $query->where('status', '1')
                        ->orderByLowestPrice()
                        ->with('price');
                },
                'offerProduct' => function ($query) use ($offer) {
                    $query->where('offer_id', $offer->id);
                }
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('cares')
            ->where('status', ProductStatus::Active->value)
            ->paginate(request('per_page', 12));


        // transform data
        $products->getCollection()->transform(function ($product) use ($offer) {

            $maxRetailPrice = $product->stockVariant?->price?->max_retail_price ?? 0;
            if ($product?->offerProduct?->discount_amount > 0) {
                ['percent' => $discountPercent, 'fixed' => $discountFixed] = PriceCalculator::resolveProductDiscount($product->offerProduct);
            } else {
                $discountPercent = $product->stockVariant?->price?->discount_percent ?? 0;
                $discountFixed   = $product->stockVariant?->price?->discount_fixed ?? 0;
            }

            return [
                'id'                 => $product->id,
                'offer_id'           => $offer->id,
                'product_id'         => $product->id,
                'product_variant_id' => $product->stockVariant?->id,
                'slug'               => $product->slug,
                'product_name'       => $product->product_name,
                'product_image'      => $product->product_image,
                'regular_price'      => $maxRetailPrice,
                'final_price'        => PriceCalculator::finalPrice($maxRetailPrice, $discountPercent, $discountFixed),
                'discount_amount'    => PriceCalculator::discountAmount($maxRetailPrice, $discountPercent, $discountFixed),
                'discount_label'     => PriceCalculator::discountLabel($discountPercent, $discountFixed),
                'is_stock' => $product->stockVariant?->stock,
                'is_prebook' => $product->stockVariant?->is_prebook,
                'purchase_point' => $product->purchase_point,
                'booking_amount' => $product->booking_amount,
                'official_warranty' => $product->has_official_warranty,
                'is_chinese' => $product->is_chinese,
                'is_unofficial' => $product->is_unofficial,
                'is_refurbished' => $product->is_refurbished,
                'is_care' => $product->cares_count > 0 ? true : false,
                'is_offer' => $product->offerProduct !== null,
                'brand' => $product?->brand,
                'avg_rating'      => floatval($product->reviews_avg_rating),

            ];
        });

        return response()->json([
            'offer' => $offer,
            'products' => $products->getCollection()->toArray(),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ],
        ]);
    }
}
