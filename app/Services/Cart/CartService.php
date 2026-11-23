<?php

namespace App\Services\Cart;

use App\Exceptions\GeneralException;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\PreOwned\PreOwned;
use App\Models\Product\ProductVariant;
use App\Repositories\Cart\CartRepository;
use App\Repositories\PreOwned\PreOwnedRepository;
use App\Repositories\Product\ProductVariantRepository;
use App\Support\VariantDiscount;
use Illuminate\Support\Facades\DB;
use Throwable;

class CartService
{
    public const MAX_QUANTITY = 10;

    public function __construct(
        private readonly CartRepository $carts,
        private readonly ProductVariantRepository $variants,
        private readonly PreOwnedRepository $preOwneds,
    ) {}

    public function findForDisplay(mixed $contactId): ?Cart
    {
        return $this->carts->findForDisplay($contactId);
    }

    /**
     * Add one unsold pre-owned unit to the customer's cart (quantity is always 1).
     *
     * @throws GeneralException when more than one is requested, it's sold, or it's already in the cart
     * @throws Throwable
     */
    public function addPreOwned(mixed $contactId, mixed $preOwnedId, mixed $variantId, int $quantity, mixed $giftId, mixed $careId): void
    {
        if ($quantity > 1) {
            throw new GeneralException('Max quantity is 1', 400);
        }

        DB::transaction(function () use ($contactId, $preOwnedId, $variantId, $giftId, $careId) {
            $cart = $this->carts->firstOrCreateForContact($contactId);

            $preOwned = $this->preOwneds->findUnsoldForUpdate($preOwnedId, $variantId);

            if (! $preOwned instanceof PreOwned) {
                throw new GeneralException('This device is already sold', 400);
            }

            if ($this->carts->hasPreOwnedItem($cart, $preOwned->id)) {
                throw new GeneralException('Already in cart', 400);
            }

            $this->carts->createItem([
                'cart_id' => $cart->id,
                'product_id' => $preOwned->product_id,
                'product_variant_id' => $preOwned->product_variant_id,
                'pre_owned_id' => $preOwned->id,
                'unit_price' => $preOwned->price ?? 0,
                'quantity' => 1,
                'booking_amount' => $preOwned->booking_amount ?? 0,
                'gift_product_variant_id' => $giftId ?? null,
                'pre_owned_care_id' => $careId ?? null,
                'discount_type' => $preOwned->discount_type ?? null,
                'discount_amount' => $preOwned->discount_amount ?? 0,
            ]);

            $this->recalculate($cart);
        });
    }

    /**
     * Add a variant to the customer's cart, or raise its quantity when it's already there.
     * The item's discount, booking amount, gift and care are refreshed either way.
     *
     * @throws GeneralException when it's out of stock (and not pre-bookable) or the quantity limit is exceeded
     * @throws Throwable
     */
    public function addVariant(mixed $contactId, mixed $variantId, int $quantity, mixed $giftId, mixed $careId): void
    {
        if ($quantity > self::MAX_QUANTITY) {
            throw new GeneralException('Max quantity is '.self::MAX_QUANTITY, 400);
        }

        DB::transaction(function () use ($contactId, $variantId, $quantity, $giftId, $careId) {
            $cart = $this->carts->firstOrCreateForContact($contactId);

            $variant = $this->variants->findWithPricingForUpdate($variantId);

            if (! $variant instanceof ProductVariant || (! $variant->stock && ! $variant->is_prebook)) {
                throw new GeneralException('Stock not available', 400);
            }

            $existingQty = $this->carts->quantityOfVariant($cart, $variant->id);

            if (($existingQty + $quantity) > self::MAX_QUANTITY) {
                throw new GeneralException('Stock limit exceeded', 400);
            }

            $offer = VariantDiscount::forVariant($variant);

            $item = $this->carts->findItemOfVariant($cart, $variant->id);

            if ($item instanceof CartItem) {
                $newQty = $item->quantity + $quantity;

                if ($newQty > self::MAX_QUANTITY) {
                    throw new GeneralException('Max quantity exceeded', 400);
                }

                $item->update([
                    'quantity' => $newQty,
                    'discount_type' => $offer['discount_type'] ?? null,
                    'discount_amount' => $offer['discount_amount'] ?? 0,
                    'booking_amount' => $variant->product?->booking_amount ?? 0,
                    'gift_product_variant_id' => $giftId ?? null,
                    'product_care_id' => $careId ?? null,
                ]);
            } else {
                $this->carts->createItem([
                    'cart_id' => $cart->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_price' => $variant->price?->max_retail_price ?? 0,
                    'quantity' => $quantity,
                    'discount_type' => $offer['discount_type'] ?? null,
                    'discount_amount' => $offer['discount_amount'] ?? 0,
                    'booking_amount' => $variant->product?->booking_amount ?? 0,
                    'gift_product_variant_id' => $giftId ?? null,
                    'product_care_id' => $careId ?? null,
                ]);
            }

            $this->recalculate($cart);
        });
    }

    /**
     * @throws GeneralException when the quantity is over the limit, the item isn't the customer's, or it's out of stock
     * @throws Throwable
     */
    public function updateQuantity(mixed $contactId, mixed $itemId, int $quantity): void
    {
        if ($quantity > self::MAX_QUANTITY) {
            throw new GeneralException('Max quantity is '.self::MAX_QUANTITY, 400);
        }

        $item = $this->carts->findItemOfContact($itemId, $contactId);

        if (! $item instanceof CartItem) {
            throw new GeneralException('Cart item not found', 404);
        }

        $variant = $this->variants->findById($item->product_variant_id);

        if (! $variant instanceof ProductVariant || ! $variant->stock) {
            throw new GeneralException('Stock not available', 400);
        }

        DB::transaction(function () use ($item, $quantity) {
            $item->update(['quantity' => $quantity]);
            $this->recalculate($item->cart);
        });
    }

    /**
     * @throws GeneralException when the item isn't the customer's
     * @throws Throwable
     */
    public function removeItem(mixed $contactId, mixed $itemId): void
    {
        $item = $this->carts->findItemOfContact($itemId, $contactId);

        if (! $item instanceof CartItem) {
            throw new GeneralException('Cart item not found', 404);
        }

        DB::transaction(function () use ($item) {
            $cart = $item->cart;
            $item->delete();
            $this->recalculate($cart);
        });
    }

    /**
     * @throws GeneralException when the item isn't the customer's
     * @throws Throwable
     */
    public function setSelected(mixed $contactId, mixed $itemId, mixed $isSelect): void
    {
        $item = $this->carts->findItemOfContact($itemId, $contactId);

        if (! $item instanceof CartItem) {
            throw new GeneralException('Cart item not found', 404);
        }

        DB::transaction(function () use ($item, $isSelect) {
            $cart = $item->cart;
            $item->update([
                'is_select' => $isSelect,
            ]);
            $this->recalculate($cart);
        });
    }

    public function recalculate(Cart $cart): void
    {
        $cart->load(['cartItems.productCare.care', 'cartItems.preOwnedCare.care']);

        $subtotal = $cart->cartItems->sum(function ($item) {
            $price = $item->unit_price * $item->quantity;

            if ($item->discount_type === 'percent') {
                $price -= (($price * $item->discount_amount) / 100);
            } elseif ($item->discount_type === 'fixed') {
                $price -= ($item->discount_amount * $item->quantity);
            }

            // care charge applied on price after discount
            $care = $item->pre_owned_id ? $item->preOwnedCare : $item->productCare;
            $carePercent = $care?->care?->value ?? 0;
            if ($carePercent > 0) {
                $price += ($price * $carePercent) / 100;
            }

            return max($price, 0);
        });

        $totalBookingAmount = $cart->cartItems->sum(
            fn ($item) => ($item->booking_amount ?? 0) * $item->quantity
        );

        $couponDiscount = min($cart->coupon_discount ?? 0, $subtotal);

        $cart->update([
            'sub_total' => $subtotal,
            'grand_total' => max($subtotal - $couponDiscount, 0),
            'total_booking_amount' => $totalBookingAmount,
        ]);
    }
}
