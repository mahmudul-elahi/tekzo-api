<?php

namespace App\Http\Controllers\Cart;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Cart\CartResource;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\PreOwned\PreOwned;
use App\Models\Product\ProductVariant;
use App\Services\Cart\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class CartController extends Controller
{
    private const MAX_QUANTITY = 10;

    public function index()
    {
        $cart = Cart::with([
            'cartItems.product.brand:id,name',
            'cartItems.product.category:id,name',
            'cartItems.variant.attributeValues.attribute',
            'cartItems.variant.product.images',
            'cartItems.preOwned',
            'cartItems' => function ($q) {
                $q->with(
                    ['giftProductVariant' => function ($q) {
                        $q->with([
                            'productVariant' => function ($q) {
                                $q->with('product:id,product_name,product_image,sku', 'attributeValues.attribute');
                            }
                        ]);
                    }, 'productCare' => function ($q) {
                        $q->with('care');
                    }, 'preOwnedCare' => function ($q) {
                        $q->with('care');
                    }]
                );
            },
        ])
            ->where('contact_id', auth()->guard('customer')->id())
            ->first();

        return successResponse($cart ? new CartResource($cart) : []);
    }

    /**
     * @throws Throwable
     */
    public function store(Request $request, CartService $cartService)
    {
        $rules = [
            'variant_id'   => ['required', 'exists:product_variants,id'],
            'quantity'     => ['required', 'integer', 'min:1'],
            'pre_owned_id' => ['nullable', 'exists:pre_owneds,id'],
            'gift_id'      => ['nullable', Rule::exists('gift_product_variants', 'id')->whereNull('deleted_at')],
            'care_id'      => !empty($request->input('pre_owned_id'))
                ? ['nullable', Rule::exists('pre_owned_cares', 'id')->whereNull('deleted_at')]
                : ['nullable', Rule::exists('product_cares', 'id')->whereNull('deleted_at')],
        ];

        $request->validate($rules);

        $qty = (int) $request->quantity;
        $customerId = auth()->guard('customer')->id();

        if (!$customerId) {
            throw new GeneralException('Unauthorized', 401);
        }

        /**
         * ========================
         * PRE-OWNED PRODUCT
         * ========================
         */
        if ($request->filled('pre_owned_id')) {

            if ($qty > 1) {
                throw new GeneralException('Max quantity is 1', 400);
            }

            DB::transaction(function () use ($request, $cartService, $customerId) {

                $cart = Cart::firstOrCreate([
                    'contact_id' => $customerId,
                ]);

                $preOwned = PreOwned::query()
                    ->where('id', $request->pre_owned_id)
                    ->where('product_variant_id', $request->variant_id)
                    ->where('is_sold', false)
                    ->lockForUpdate()
                    ->first();

                if (!$preOwned) {
                    throw new GeneralException('This device is already sold', 400);
                }

                $exists = CartItem::where('cart_id', $cart->id)
                    ->where('pre_owned_id', $preOwned->id)
                    ->exists();

                if ($exists) {
                    throw new GeneralException('Already in cart', 400);
                }

                CartItem::create([
                    'cart_id'                 => $cart->id,
                    'product_id'              => $preOwned->product_id,
                    'product_variant_id'      => $preOwned->product_variant_id,
                    'pre_owned_id'            => $preOwned->id,
                    'unit_price'              => $preOwned->price ?? 0,
                    'quantity'                => 1,
                    'booking_amount'          => $preOwned->booking_amount ?? 0,
                    'gift_product_variant_id' => $request->gift_id ?? null,
                    'pre_owned_care_id'       => $request->care_id ?? null,
                    'discount_type'           => $preOwned->discount_type ?? null,
                    'discount_amount'         => $preOwned->discount_amount ?? 0,
                ]);

                $cartService->recalculate($cart);
            });

            return responseMessage('Cart added');
        }

        /**
         * ========================
         * NORMAL PRODUCT
         * ========================
         */
        if ($qty > self::MAX_QUANTITY) {
            throw new GeneralException('Max quantity is ' . self::MAX_QUANTITY, 400);
        }

        DB::transaction(function () use ($request, $customerId, $qty, $cartService) {

            $cart = Cart::firstOrCreate([
                'contact_id' => $customerId,
            ]);

            $variant = ProductVariant::query()
                ->with(['price', 'offerProduct', 'product'])
                ->lockForUpdate()
                ->find($request->variant_id);

            if (!$variant || (!$variant->stock && !$variant->is_prebook)) {
                throw new GeneralException('Stock not available', 400);
            }
            /**
             * ------------------------
             * STOCK OVERFLOW PROTECTION
             * ------------------------
             */
            $existingQty = CartItem::where('cart_id', $cart->id)
                ->where('product_variant_id', $variant->id)
                ->sum('quantity');

            if (($existingQty + $qty) > self::MAX_QUANTITY) {
                throw new GeneralException('Stock limit exceeded', 400);
            }

            /**
             * ------------------------
             * DISCOUNT CALCULATION
             * ------------------------
             */
            if ($variant?->offerProduct?->discount_amount > 0) {
                $offer = $this->handelDiscountType($variant->offerProduct);
            } else {
                $offer = [
                    'discount_type' =>
                    $variant->price?->discount_percent > 0 ? 'percent' : ($variant->price?->discount_fixed > 0 ? 'fixed' : null),

                    'discount_amount' =>
                    $variant->price?->discount_percent > 0
                        ? $variant->price->discount_percent
                        : ($variant->price?->discount_fixed ?? 0),
                ];
            }

            /**
             * ------------------------
             * CART ITEM HANDLING
             * ------------------------
             */
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_variant_id', $variant->id)
                ->first();

            if ($item) {

                $newQty = $item->quantity + $qty;

                if ($newQty > self::MAX_QUANTITY) {
                    throw new GeneralException('Max quantity exceeded', 400);
                }

                $item->update([
                    'quantity' => $newQty,
                    'discount_type' => $offer['discount_type'] ?? null,
                    'discount_amount' => $offer['discount_amount'] ?? 0,
                    'booking_amount' => $variant->product?->booking_amount ?? 0,
                    'gift_product_variant_id' => $request['gift_id'] ?? null,
                    'product_care_id' => $request['care_id'] ?? null,
                ]);
            } else {

                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_price' => $variant->price?->max_retail_price ?? 0,
                    'quantity' => $qty,
                    'discount_type' => $offer['discount_type'] ?? null,
                    'discount_amount' => $offer['discount_amount'] ?? 0,
                    'booking_amount' => $variant->product?->booking_amount ?? 0,
                    'gift_product_variant_id' => $request['gift_id'] ?? null,
                    'product_care_id' => $request['care_id'] ?? null,
                ]);
            }

            $cartService->recalculate($cart);
        });

        return responseMessage('Cart added');
    }

    private function handelDiscountType($offerProduct)
    {

        return [
            'discount_type' => $offerProduct->discount_type === 'percentage' ? 'percent' : 'fixed',
            'discount_amount' => $offerProduct->discount_amount,
        ];
    }

    /**
     * @throws Throwable
     */
    public function update(Request $request, $id, CartService $cartService)
    {
        $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $qty = (int)$request->quantity;

        if ($qty > self::MAX_QUANTITY) {
            return responseMessage('Max quantity is ' . self::MAX_QUANTITY, 400);
        }

        $item = CartItem::query()->where('id', $id)
            ->whereHas('cart', fn($q) => $q->where('contact_id', auth()->guard('customer')->id()))
            ->first();

        if (!$item) {
            return responseMessage('Cart item not found', 404);
        }

        $variant = ProductVariant::query()->find($item->product_variant_id);

        if (!$variant || !$variant->stock) {
            return responseMessage('Stock not available', 400);
        }


        DB::transaction(function () use ($item, $qty, $cartService) {
            $item->update(['quantity' => $qty]);
            $cartService->recalculate($item->cart);
        });

        return responseMessage('Cart item updated');
    }

    /**
     * @throws Throwable
     */
    public function destroy($id, CartService $cartService)
    {
        $item = CartItem::query()->where('id', $id)
            ->whereHas('cart', fn($q) => $q->where('contact_id', auth()->guard('customer')->id()))
            ->first();

        if (!$item) {
            return responseMessage('Cart item not found', 404);
        }

        DB::transaction(function () use ($item, $cartService) {
            $cart = $item->cart;
            $item->delete();
            $cartService->recalculate($cart);
        });

        return responseMessage('Cart item removed');
    }

    public function toggleSelect(Request $request, CartService $cartService)
    {
        $request->validate([
            'cart_item_id' => ['required', 'exists:cart_items,id'],
            'is_select' => ['required', 'boolean'],
        ]);

        $customerId = auth()->guard('customer')->id();

        $item = CartItem::query()
            ->where('id', $request->cart_item_id)
            ->whereHas('cart', fn($q) => $q->where('contact_id', $customerId))
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

        return responseMessage('Cart item updated successfully');
    }
}
