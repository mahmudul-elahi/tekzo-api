<?php

namespace App\Repositories\Order;

use App\Models\Order\Order;
use App\Models\Order\OrderPaymentHistory;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository extends BaseRepository
{
    public function __construct(Order $order)
    {
        $this->model = $order;
    }

    /**
     * A customer's orders, newest first, with their total quantity; optionally filtered by invoice search and status.
     */
    public function paginateForContact(mixed $contactId, mixed $search, mixed $status, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->withSum('orderLists', 'quantity')
            ->when($search, fn ($q) => $q->where('invoice_number', 'like', "%{$search}%"))
            ->where('contact_id', $contactId)
            ->orderByDesc('id')
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->paginate($perPage);
    }

    /**
     * The customer's 5 most recent orders.
     *
     * @return Collection<int, Order>
     */
    public function recentForContact(mixed $contactId): Collection
    {
        return $this->query()
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * One of the customer's orders.
     *
     * @param  array<int|string, mixed>  $relations
     */
    public function findForContact(mixed $orderId, mixed $contactId, array $relations = []): ?Order
    {
        return $this->query()
            ->where('contact_id', $contactId)
            ->where('id', $orderId)
            ->when($relations !== [], fn ($q) => $q->with($relations))
            ->first();
    }

    public function countForContact(mixed $contactId): int
    {
        return $this->query()
            ->where('contact_id', $contactId)
            ->count();
    }

    public function findByTransactionId(mixed $transactionId): ?Order
    {
        return $this->query()
            ->where('transaction_id', $transactionId)
            ->first();
    }

    /**
     * The order for a payment transaction, row-locked for the current transaction.
     */
    public function findByTransactionIdForUpdate(mixed $transactionId): ?Order
    {
        return $this->query()->where('transaction_id', $transactionId)->lockForUpdate()->first();
    }

    public function findByInvoiceNumber(mixed $invoiceNumber): ?Order
    {
        return $this->query()
            ->where('invoice_number', $invoiceNumber)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Order
    {
        return $this->query()->create($attributes);
    }

    /**
     * An order's payment attempts, newest first.
     *
     * @return Collection<int, OrderPaymentHistory>
     */
    public function paymentHistories(mixed $orderId): Collection
    {
        return OrderPaymentHistory::query()
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->get();
    }
}
