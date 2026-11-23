<?php

namespace App\Repositories\Wishlist;

use App\Models\Wishlist\Wishlist;
use App\Models\Wishlist\WishlistItem;
use App\Repositories\BaseRepository;

class WishlistRepository extends BaseRepository
{
    public function __construct(Wishlist $wishlist)
    {
        $this->model = $wishlist;
    }

    /**
     * A customer's wishlist with everything the wishlist page shows.
     */
    public function findForDisplay(mixed $contactId): ?Wishlist
    {
        return $this->query()->with([
            'items.product.brand:id,name',
            'items.product.category:id,name',
            'items.variant.attributeValues.attribute',
            'items.variant.product.images',
        ])
            ->where('contact_id', $contactId)
            ->first();
    }

    public function firstOrCreateForContact(mixed $contactId): Wishlist
    {
        return $this->query()->firstOrCreate([
            'contact_id' => $contactId,
        ]);
    }

    /**
     * A wishlist item, only when it belongs to the customer's wishlist.
     */
    public function findItemOfContact(mixed $itemId, mixed $contactId): ?WishlistItem
    {
        return WishlistItem::query()->where('id', $itemId)
            ->whereHas('wishlist', fn ($q) => $q->where('contact_id', $contactId))
            ->first();
    }

    public function findItemOfVariant(Wishlist $wishlist, int $variantId): ?WishlistItem
    {
        return WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('product_variant_id', $variantId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(array $attributes): WishlistItem
    {
        return WishlistItem::query()->create($attributes);
    }
}
