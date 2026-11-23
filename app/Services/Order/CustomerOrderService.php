<?php

namespace App\Services\Order;

use App\Enums\OnlinePaymentStatusEnum;
use App\Exceptions\GeneralException;
use App\Models\Order\Order;
use App\Models\Order\OrderPaymentHistory;
use App\Repositories\Cart\CartRepository;
use App\Repositories\Order\OrderRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * A signed-in customer's own orders.
 */
class CustomerOrderService
{
    public function __construct(
        private OrderRepository $orders,
        private CartRepository $carts,
    ) {}

    public function paginate(mixed $contactId, mixed $search, mixed $status, int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginateForContact($contactId, $search, $status, $perPage);
    }

    /**
     * @return Collection<int, Order>
     */
    public function recent(mixed $contactId): Collection
    {
        return $this->orders->recentForContact($contactId);
    }

    /**
     * @param  array<int|string, mixed>  $relations
     */
    public function find(mixed $orderId, mixed $contactId, array $relations = []): ?Order
    {
        return $this->orders->findForContact($orderId, $contactId, $relations);
    }

    /**
     * @return array{totalOrder: int, totalCart: int, totalWishlist: int}
     */
    public function statistics(mixed $contactId): array
    {
        return [
            'totalOrder' => $this->orders->countForContact($contactId),
            'totalCart' => $this->carts->countForContact($contactId),
            'totalWishlist' => 0,
        ];
    }

    /**
     * @throws GeneralException when the order is already paid or nothing is due
     */
    public function ensureRepayable(Order $order): void
    {
        if ($order->payment_status == OnlinePaymentStatusEnum::Successful->value) {
            throw new GeneralException('Order has already been paid successfully.', 400);
        }

        if ($order->due_amount <= 0) {
            throw new GeneralException('No due amount remaining for this order.', 400);
        }
    }

    /**
     * @return Collection<int, OrderPaymentHistory>
     */
    public function paymentHistories(Order $order): Collection
    {
        return $this->orders->paymentHistories($order->id);
    }
}
