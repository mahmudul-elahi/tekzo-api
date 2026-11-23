<?php

namespace App\Services\Wishlist;

use App\Exceptions\GeneralException;
use App\Models\Product\ProductVariant;
use App\Models\Wishlist\Wishlist;
use App\Models\Wishlist\WishlistItem;
use App\Repositories\Product\ProductVariantRepository;
use App\Repositories\Wishlist\WishlistRepository;
use App\Support\VariantDiscount;
use Illuminate\Support\Facades\DB;
use Throwable;

class WishlistService
{
    public const MAX_QUANTITY = 10;

    public function __construct(
        private readonly WishlistRepository $wishlists,
        private readonly ProductVariantRepository $variants,
    ) {}

    public function findForDisplay(mixed $contactId): ?Wishlist
    {
        return $this->wishlists->findForDisplay($contactId);
    }

    /**
     * Add an in-stock variant to the customer's wishlist, or raise its quantity when it's already there.
     *
     * @throws GeneralException when the quantity is over the limit or the variant is out of stock
     * @throws Throwable
     */
    public function addVariant(mixed $contactId, mixed $variantId, int $quantity): void
    {
        if ($quantity > self::MAX_QUANTITY) {
            throw new GeneralException('Max quantity is '.self::MAX_QUANTITY, 400);
        }

        $variant = $this->variants->findWithPricing($variantId);

        if (! $variant instanceof ProductVariant || ! $variant->stock) {
            throw new GeneralException('Stock not available', 400);
        }

        $offer = VariantDiscount::forVariant($variant);

        DB::transaction(function () use ($contactId, $variant, $quantity, $offer) {
            $wishlist = $this->wishlists->firstOrCreateForContact($contactId);

            $item = $this->wishlists->findItemOfVariant($wishlist, $variant->id);

            if ($item instanceof WishlistItem) {
                $newQty = $item->quantity + $quantity;

                if ($newQty > self::MAX_QUANTITY) {
                    throw new GeneralException('Max quantity is '.self::MAX_QUANTITY, 400);
                }

                $item->update(['quantity' => $newQty]);
            } else {
                $this->wishlists->createItem([
                    'wishlist_id' => $wishlist->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_price' => $variant->price->max_retail_price ?? 0,
                    'quantity' => $quantity,
                    'discount_type' => $offer['discount_type'] ?? null,
                    'discount_amount' => ($offer['discount_amount'] ?? null) ?? 0,
                ]);
            }

            $this->recalculate($wishlist);
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

        $item = $this->wishlists->findItemOfContact($itemId, $contactId);

        if (! $item instanceof WishlistItem) {
            throw new GeneralException('Wishlist item not found', 404);
        }

        $variant = $this->variants->findById($item->product_variant_id);

        if (! $variant instanceof ProductVariant || ! $variant->stock) {
            throw new GeneralException('Stock not available', 400);
        }

        DB::transaction(function () use ($item, $quantity) {
            $item->update(['quantity' => $quantity]);
            $this->recalculate($item->wishlist);
        });
    }

    /**
     * @throws GeneralException when the item isn't the customer's
     * @throws Throwable
     */
    public function removeItem(mixed $contactId, mixed $itemId): void
    {
        $item = $this->wishlists->findItemOfContact($itemId, $contactId);

        if (! $item instanceof WishlistItem) {
            throw new GeneralException('Wishlist item not found', 404);
        }

        DB::transaction(function () use ($item) {
            $wishlist = $item->wishlist;
            $item->delete();
            $this->recalculate($wishlist);
        });
    }

    /**
     * @throws GeneralException when the item isn't the customer's
     * @throws Throwable
     */
    public function setSelected(mixed $contactId, mixed $itemId, mixed $isSelect): void
    {
        $item = $this->wishlists->findItemOfContact($itemId, $contactId);

        if (! $item instanceof WishlistItem) {
            throw new GeneralException('Cart item not found', 404);
        }

        DB::transaction(function () use ($item, $isSelect) {
            $wishlist = $item->wishlist;
            $item->update([
                'is_select' => $isSelect,
            ]);
            $this->recalculate($wishlist);
        });
    }

    public function recalculate(Wishlist $cart): void
    {
        $cart->load('items');

        $subtotal = $cart->items->sum(function ($item) {
            $price = $item->unit_price * $item->quantity;

            if ($item->discount_type === 'percent') {
                $price -= (($price * $item->discount_amount) / 100);
            } elseif ($item->discount_type === 'fixed') {
                $price -= $item->discount_amount;
            }

            return max($price, 0);
        });

        $cart->update([
            'sub_total' => $subtotal,
            'grand_total' => $subtotal,
        ]);
    }
}
