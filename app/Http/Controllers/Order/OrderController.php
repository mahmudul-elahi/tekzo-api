<?php

namespace App\Http\Controllers\Order;

use App\Enums\OnlinePaymentStatusEnum;
use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Cart\Cart;
use App\Models\Order\Order;
use App\Models\Order\OrderPaymentHistory;
use App\Services\Bkash\BkashService;
use App\Services\SSCommerz\SSLCommerzPaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderController extends Controller
{
    public function index(Request $request): LengthAwarePaginator
    {
        $query = Order::query()
            ->withSum('orderLists', 'quantity')
            ->when($request->search, fn ($q) => $q->where('invoice_number', 'like', "%{$request->search}%"))
            ->where('contact_id', $this->getCustomer())
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query->paginate(clampPerPage($request->get('per_page'), 10, 48))
            ->through(function ($order) {
                $statusEnum = StatusEnum::from($order->status);

                return [
                    'id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'payment_method' => $order->payment_method,
                    'payment_status' => $this->formatPaymentStatus($order),
                    'shipping_charge' => $order->shipping_charge ?? 0,
                    'sub_total' => $order->sub_total,
                    'grand_total' => $order->grand_total,
                    'due_amount' => $order->due_amount,
                    'payable_amount' => $order->payable_amount,
                    'status' => [
                        'name' => $statusEnum->name,
                        'color' => $statusEnum->color(),
                    ],
                    'total_quantity' => $order->order_lists_sum_quantity ?? 0,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            });
    }

    private function formatPaymentStatus($row): array
    {
        // Normalize method label
        $method = match ($row->payment_method) {
            'cod' => 'COD',
            'online' => 'Online',
            'bkash' => 'Bkash',
            default => ucfirst($row->payment_method),
        };

        // COD has no status
        if ($row->payment_method === 'cod') {
            return [
                'method' => $method,
                'status' => 'Pending',
            ];
        }

        // Centralized status mapping
        $statusText = match ((int) $row->payment_status) {
            OnlinePaymentStatusEnum::Initiated->value => 'Initiated',
            OnlinePaymentStatusEnum::Processing->value => 'Processing',
            OnlinePaymentStatusEnum::Successful->value => 'Successful',
            OnlinePaymentStatusEnum::Failed->value => 'Failed',
            OnlinePaymentStatusEnum::Canceled->value => 'Cancelled',
            OnlinePaymentStatusEnum::Unsuccessful->value => 'Unsuccessful',
            OnlinePaymentStatusEnum::PartiallyPaid->value => 'Partially Paid',
            default => 'Pending',
        };

        return [
            'method' => $method,
            'status' => $statusText,
        ];
    }

    public function recentOrder()
    {
        $orders = Order::query()
            ->where('contact_id', $this->getCustomer())
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                $statusEnum = StatusEnum::from($order->status);

                return [
                    ...$order->toArray(),
                    'payment_status' => $this->formatPaymentStatus($order),
                    'status' => [
                        'name' => $statusEnum->name,
                        'color' => $statusEnum->color(),
                    ],
                ];
            });

        return successResponse($orders);
    }

    public function orderDetail($id)
    {
        $order = Order::query()
            ->where('contact_id', $this->getCustomer())
            ->where('id', $id)
            ->first();

        if (! $order) {
            return responseMessage('Order not found', 404);
        }

        $statusEnum = StatusEnum::from($order->status);

        return successResponse([
            'id' => $order->id,
            'invoiceNumber' => $order->invoice_number,
            'grandTotal' => $order->grand_total,
            'subTotal' => $order->sub_total,
            'shippingCharge' => $order->shipping_charge,
            'paymentMethod' => $order->payment_method,
            'paymentStatus' => $this->formatPaymentStatus($order),
            'dueAmount' => $order->due_amount,
            'status' => [
                'name' => $statusEnum->name,
                'color' => $statusEnum->color(),
            ],
            'date' => Carbon::parse($order->updated_at)->format('Y-m-d'),
            'time' => Carbon::parse($order->updated_at)->format('h:i A'),
            'createdAt' => $order->created_at,
            'updatedAt' => $order->updated_at,
        ]);
    }

    public function statistics()
    {
        $customerId = $this->getCustomer();

        $totalOrder = Order::query()
            ->where('contact_id', $customerId)
            ->count();
        $totalCart = Cart::query()
            ->where('contact_id', $customerId)
            ->count();
        $totalWishlist = 0;

        return successResponse([
            'totalOrder' => $totalOrder,
            'totalCart' => $totalCart,
            'totalWishlist' => $totalWishlist,
        ]);
    }

    public function repay(Request $request, $orderId)
    {
        $order = Order::query()
            ->where('contact_id', $this->getCustomer())
            ->where('id', $orderId)
            ->with(['contact', 'area', 'district'])
            ->first();

        if (! $order) {
            return responseMessage('Order not found', 404);
        }

        $request->validate([
            'payment_method' => ['required', 'in:online,bkash'],
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$order->due_amount],
        ]);

        if ($order->payment_status == OnlinePaymentStatusEnum::Successful->value) {
            return responseMessage('Order has already been paid successfully.', 400);
        }

        if ($order->due_amount <= 0) {
            return responseMessage('No due amount remaining for this order.', 400);
        }

        $amount = (float) $request->amount;

        if ($request->payment_method === 'online') {
            $ssl = new SSLCommerzPaymentService;

            return response()->json($ssl->initSSLCommerz($order, $amount));
        }

        if ($request->payment_method === 'bkash') {
            $result = BkashService::make()->createPayment([
                'payerReference' => (string) $order->id,
                'amount' => $amount,
                'merchantInvoiceNumber' => $order->invoice_number,
            ]);

            return response()->json($result);
        }

        return responseMessage('This payment method does not support repayment.', 400);
    }

    public function paymentHistory(int $orderId)
    {
        $order = Order::query()
            ->where('contact_id', $this->getCustomer())
            ->where('id', $orderId)
            ->first();

        if (! $order) {
            return responseMessage('Order not found', 404);
        }

        $histories = OrderPaymentHistory::query()
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'payment_method' => $h->payment_method,
                'amount' => $h->amount,
                'transaction_id' => $h->transaction_id,
                'status' => $h->status,
                'created_at' => $h->created_at,
            ]);

        return successResponse([
            'order_id' => $order->id,
            'invoice_number' => $order->invoice_number,
            'grand_total' => $order->grand_total,
            'payable_amount' => $order->payable_amount,
            'due_amount' => $order->due_amount,
            'histories' => $histories,
        ]);
    }

    private function getCustomer()
    {
        return auth()->guard('customer')->id();
    }
}
