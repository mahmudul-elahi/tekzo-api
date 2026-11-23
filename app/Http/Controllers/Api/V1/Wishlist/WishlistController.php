<?php

namespace App\Http\Controllers\Api\V1\Wishlist;

use App\Http\Controllers\Controller;
use App\Http\Resources\Wishlist\WishlistResource;
use App\Models\Product\ProductVariant;
use App\Models\Wishlist\Wishlist;
use App\Models\Wishlist\WishlistItem;
use App\Services\Wishlist\WishlistService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class WishlistController extends Controller
{
    private const MAX_QUANTITY = 10;

    public function index()
    {
        $cart = Wishlist::query()->with([
            'items.product.brand:id,name',
            'items.product.category:id,name',
            'items.variant.attributeValues.attribute',
            'items.variant.product.images',
        ])
            ->where('contact_id', auth()->guard('customer')->id())
            ->first();

        return successResponse($cart ? new WishlistResource($cart) : []);
    }

    public function store(Request $request, WishlistService $cartService)
    {
        $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $qty = (int)$request->quantity;

        if ($qty > self::MAX_QUANTITY) {
            return responseMessage('Max quantity is ' . self::MAX_QUANTITY, 400);
        }

        $variant = ProductVariant::with(['price', 'offerProduct'])->find($request->variant_id);

        if (!$variant || !$variant->stock) {
            return responseMessage('Stock not available', 400);
        }

        if ($variant?->offerProduct?->discount_amount > 0) {
            $aviableOfferProduct = $this->handelDiscountType($variant->offerProduct);
        } else {
            $aviableOfferProduct = [
                'discount_type' => $variant->price?->discount_percent > 0 ? 'percent' : ($variant->price?->discount_fixed > 0 ? 'fixed' : null),
                'discount_amount' => $variant->price?->discount_percent > 0 ? $variant->price->discount_percent : ($variant->price?->discount_fixed ?? 0),
            ];
        }

        DB::transaction(function () use ($variant, $qty, $cartService, $aviableOfferProduct) {

            $wishtlist = Wishlist::query()->firstOrCreate([
                'contact_id' => auth()->guard('customer')->id(),
            ]);

            $item = WishlistItem::query()
                ->where('wishlist_id', $wishtlist->id)
                ->where('product_variant_id', $variant->id)
                ->first();

            if ($item) {
                $newQty = $item->quantity + $qty;

                if ($newQty > self::MAX_QUANTITY) {
                    throw new \Exception('Max quantity is ' . self::MAX_QUANTITY, 400);
                }

                $item->update(['quantity' => $newQty]);
            } else {
                $aviableOfferType = $aviableOfferProduct['discount_type'] ?? null;
                $aviableOfferAmount = $aviableOfferProduct['discount_amount'] ?? null;

                WishlistItem::query()->create([
                    'wishlist_id' => $wishtlist->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_price' => $variant->price->max_retail_price ?? 0,
                    'quantity' => $qty,
                    'discount_type'      => $aviableOfferType ?? null,
                    'discount_amount'    => $aviableOfferAmount ?? 0,
                ]);
            }

            $cartService->recalculate($wishtlist);
        });

        return responseMessage('Wishlist added successfully');
    }

    public function update(Request $request, $id, WishlistService $cartService)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $qty = (int)$request->quantity;

        if ($qty > self::MAX_QUANTITY) {
            return responseMessage('Max quantity is ' . self::MAX_QUANTITY, 400);
        }

        $item = WishlistItem::query()->where('id', $id)
            ->whereHas('wishlist', fn($q) => $q->where('contact_id', auth()->guard('customer')->id()))
            ->first();

        if (!$item) {
            return responseMessage('Wishlist item not found', 404);
        }

        $variant = ProductVariant::query()->find($item->product_variant_id);

        if (!$variant || !$variant->stock) {
            return responseMessage('Stock not available', 400);
        }


        DB::transaction(function () use ($item, $qty, $cartService) {
            $item->update(['quantity' => $qty]);
            $cartService->recalculate($item->cart);
        });

        return responseMessage('Wishlist item updated');
    }

    /**
     * @throws Throwable
     */
    public function destroy($id, WishlistService $cartService)
    {
        $item = WishlistItem::query()->where('id', $id)
            ->whereHas('wishlist', fn($q) => $q->where('contact_id', auth()->guard('customer')->id()))
            ->first();

        if (!$item) {
            return responseMessage('Wishlist item not found', 404);
        }

        DB::transaction(function () use ($item, $cartService) {
            $cart = $item->wishlist;
            $item->delete();
            $cartService->recalculate($cart);
        });

        return responseMessage('Cart item removed');
    }

    public function toggleSelect(Request $request, WishlistService $cartService)
    {
        $request->validate([
            'cart_item_id' => ['required', 'exists:wishlist_items,id'],
            'is_select' => ['required', 'boolean'],
        ]);

        $customerId = auth()->guard('customer')->id();

        $item = WishlistItem::query()
            ->where('id', $request->cart_item_id)
            ->whereHas('wishlist', fn($q) => $q->where('contact_id', $customerId))
            ->first();

        if (!$item) {
            return responseMessage('Cart item not found', 404);
        }

        $isSelect = $request->is_select;

        DB::transaction(function () use ($item, $cartService, $isSelect) {
            $cart = $item->cart;
            $item->update([
                'is_select' => $isSelect
            ]);
            $cartService->recalculate($cart);
        });

        return responseMessage('Wishlist item updated successfully');
    }

    private function handelDiscountType($offerProduct)
    {
        return [
            'discount_type' => $offerProduct->discount_type === 'percentage' ? 'percent' : 'fixed',
            'discount_amount' => $offerProduct->discount_amount,
        ];
    }
}
