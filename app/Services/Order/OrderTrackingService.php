<?php

namespace App\Services\Order;

use App\Models\Order\Order;
use App\Models\ProductVerify\ProductVerify;
use App\Repositories\Order\OrderRepository;
use App\Repositories\ProductVerify\ProductVerifyRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Public order tracking and product authenticity lookups.
 */
class OrderTrackingService
{
    public function __construct(
        private OrderRepository $orders,
        private ProductVerifyRepository $productVerifies,
    ) {}

    public function findByInvoiceNumber(mixed $invoiceNumber): ?Order
    {
        return $this->orders->findByInvoiceNumber($invoiceNumber);
    }

    /**
     * @return Collection<int, ProductVerify>
     */
    public function verifiedProducts(string $phone): Collection
    {
        return $this->productVerifies->forPhone($phone);
    }
}
