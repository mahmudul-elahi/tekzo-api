<?php

namespace App\Repositories\Cart;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Repositories\BaseRepository;
use Illuminate\Support\Collection;

class CartRepository extends BaseRepository
{
    public function __construct(Cart $cart)
    {
        $this->model = $cart;
    }

    /**
     * A customer's cart with everything the cart page shows.
     */
    public function findForDisplay(mixed $contactId): ?Cart
    {
        return $this->query()
            ->with([
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
                                },
                            ]);
                        }, 'productCare' => function ($q) {
                            $q->with('care');
                        }, 'preOwnedCare' => function ($q) {
                            $q->with('care');
                        }]
                    );
                },
            ])
            ->where('contact_id', $contactId)
            ->first();
    }

    /**
     * A customer's cart with its items' products and the applied coupon.
     */
    public function findForCheckout(mixed $contactId): ?Cart
    {
        return $this->query()
            ->where('contact_id', $contactId)
            ->with(['cartItems.product', 'coupon'])
            ->first();
    }

    public function countForContact(mixed $contactId): int
    {
        return $this->query()
            ->where('contact_id', $contactId)
            ->count();
    }

    /**
     * @param  Collection<int, int>  $itemIds
     */
    public function deleteItems(Collection $itemIds): void
    {
        CartItem::query()->whereIn('id', $itemIds)->delete();
    }

    /**
     * A customer's cart with each item's product category and brand (for coupon scopes).
     */
    public function findWithItemProducts(mixed $contactId): ?Cart
    {
        return $this->query()
            ->with([
                'cartItems.product.category',
                'cartItems.product.brand',
            ])
            ->where('contact_id', $contactId)
            ->first();
    }

    public function findForContact(mixed $contactId): ?Cart
    {
        return $this->query()->where('contact_id', $contactId)->first();
    }

    public function firstOrCreateForContact(mixed $contactId): Cart
    {
        return Cart::firstOrCreate([
            'contact_id' => $contactId,
        ]);
    }

    /**
     * A cart item, only when it belongs to the customer's cart.
     */
    public function findItemOfContact(mixed $itemId, mixed $contactId): ?CartItem
    {
        return CartItem::query()->where('id', $itemId)
            ->whereHas('cart', fn ($q) => $q->where('contact_id', $contactId))
            ->first();
    }

    public function hasPreOwnedItem(Cart $cart, int $preOwnedId): bool
    {
        return CartItem::where('cart_id', $cart->id)
            ->where('pre_owned_id', $preOwnedId)
            ->exists();
    }

    public function quantityOfVariant(Cart $cart, int $variantId): int|string
    {
        return CartItem::where('cart_id', $cart->id)
            ->where('product_variant_id', $variantId)
            ->sum('quantity');
    }

    public function findItemOfVariant(Cart $cart, int $variantId): ?CartItem
    {
        return CartItem::where('cart_id', $cart->id)
            ->where('product_variant_id', $variantId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(array $attributes): CartItem
    {
        return CartItem::create($attributes);
    }
}
