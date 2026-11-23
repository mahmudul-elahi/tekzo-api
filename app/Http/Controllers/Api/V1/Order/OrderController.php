<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Order\CustomerOrderResource;
use App\Http\Resources\Order\OrderDetailResource;
use App\Http\Resources\Order\PaymentHistoryResource;
use App\Http\Resources\Order\RecentOrderResource;
use App\Services\Order\CustomerOrderService;
use App\Services\Order\OrderPaymentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private CustomerOrderService $orderService,
        private OrderPaymentService $paymentService,
    ) {}

    /**
     * Returned as a bare paginator (no "data" wrapper), as the frontend expects.
     */
    public function index(Request $request): LengthAwarePaginator
    {
        return $this->orderService
            ->paginate(
                $this->customerId(),
                $request->search,
                $request->filled('status') ? $request->status : null,
                clampPerPage($request->get('per_page'), 10, 48),
            )
            ->through(fn ($order) => (new CustomerOrderResource($order))->resolve($request));
    }

    public function recentOrder(Request $request): JsonResponse
    {
        return successResponse(
            $this->orderService->recent($this->customerId())
                ->map(fn ($order) => (new RecentOrderResource($order))->resolve($request))
        );
    }

    public function orderDetail(string $id): JsonResponse
    {
        $order = $this->orderService->find($id, $this->customerId());

        if (! $order) {
            return responseMessage('Order not found', 404);
        }

        return successResponse(new OrderDetailResource($order));
    }

    public function statistics(): JsonResponse
    {
        return successResponse($this->orderService->statistics($this->customerId()));
    }

    /**
     * Start a new online/bKash payment for an order's remaining due amount (or part of it).
     * The order is looked up before validating, so a foreign order is a 404 even with bad input.
     *
     * @throws GeneralException
     */
    public function repay(Request $request, string $orderId): JsonResponse
    {
        $order = $this->orderService->find($orderId, $this->customerId(), ['contact', 'area', 'district']);

        if (! $order) {
            return responseMessage('Order not found', 404);
        }

        $request->validate([
            'payment_method' => ['required', 'in:online,bkash'],
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$order->due_amount],
        ]);

        $this->orderService->ensureRepayable($order);

        $amount = (float) $request->amount;

        if ($request->payment_method === 'online') {
            return response()->json($this->paymentService->startSslCommerz($order, $amount));
        }

        if ($request->payment_method === 'bkash') {
            return response()->json($this->paymentService->startBkash((string) $order->id, $amount, $order->invoice_number));
        }

        return responseMessage('This payment method does not support repayment.', 400);
    }

    public function paymentHistory(int $orderId, Request $request): JsonResponse
    {
        $order = $this->orderService->find($orderId, $this->customerId());

        if (! $order) {
            return responseMessage('Order not found', 404);
        }

        return successResponse([
            'order_id' => $order->id,
            'invoice_number' => $order->invoice_number,
            'grand_total' => $order->grand_total,
            'payable_amount' => $order->payable_amount,
            'due_amount' => $order->due_amount,
            'histories' => $this->orderService->paymentHistories($order)
                ->map(fn ($history) => (new PaymentHistoryResource($history))->resolve($request)),
        ]);
    }

    private function customerId(): mixed
    {
        return auth()->guard('customer')->id();
    }
}
