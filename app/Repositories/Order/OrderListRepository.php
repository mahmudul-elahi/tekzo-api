<?php

namespace App\Repositories\Order;

use App\Enums\StatusEnum;
use App\Models\Order\Order;
use App\Models\Order\OrderList;
use App\Models\Product\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Order lines of a customer's completed (delivered) orders.
 */
class OrderListRepository
{
    public function findCompletedForContact(mixed $orderListId, mixed $contactId): ?OrderList
    {
        return OrderList::query()
            ->whereHas('order', fn ($q) => $q->where('contact_id', $contactId)->where('status', StatusEnum::Complete->value))
            ->find($orderListId);
    }

    /**
     * Every delivered line with its product, variant attributes, order, and how many reviews the customer left.
     *
     * @return Collection<int, OrderList>
     */
    public function completedForContactWithReviewCount(mixed $contactId): Collection
    {
        return $this->completedForContact($contactId)
            ->with(['product', 'variant.attributeValues', 'order'])
            ->withCount(['review' => function ($query) use ($contactId) {
                $query->where('contact_id', $contactId);
            }])
            ->get();
    }

    public function findCompletedWithDetailsForContact(mixed $orderListId, mixed $contactId): ?OrderList
    {
        return OrderList::query()
            ->where('id', $orderListId)
            ->whereHas('order', function ($query) use ($contactId) {
                $query->where('contact_id', $contactId)
                    ->where('status', StatusEnum::Complete->value);
            })
            ->with(['product', 'variant.attributeValues', 'order'])
            ->first();
    }

    /**
     * A completed order of the customer with its lines' products and variant attributes.
     */
    public function findCompletedOrderWithLines(mixed $orderId, mixed $contactId): ?Order
    {
        return Order::with(['orderLists' => function ($query) {
            $query->select('id', 'order_id', 'product_id', 'product_variant_id')
                ->with([
                    'variant' => function ($query) {
                        $query->with(['attributeValues']);
                    },
                    'product:id,product_name',
                ]);
        }])
            ->where('id', $orderId)
            ->where('status', StatusEnum::Complete->value)
            ->where('contact_id', $contactId)
            ->select(['id', 'status'])
            ->first();
    }

    /**
     * Path of the product's primary image for an attribute value (color).
     */
    public function primaryImagePath(mixed $productId, mixed $attributeValueId): ?string
    {
        $images = ProductImage::query()
            ->where('product_id', $productId)
            ->where('attribute_value_id', $attributeValueId)
            ->where('is_primary', true)
            ->first();

        return $images?->image_path;
    }

    private function completedForContact(mixed $contactId): Builder
    {
        return OrderList::query()
            ->whereHas('order', function ($query) use ($contactId) {
                $query->where('contact_id', $contactId)
                    ->where('status', StatusEnum::Complete->value);
            });
    }
}
