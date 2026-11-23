<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\Order\ProductVerifyResource;
use App\Services\Order\OrderTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderTrackController extends Controller
{
    public function __construct(private OrderTrackingService $trackingService) {}

    public function orderTracking(Request $request): JsonResponse
    {
        $request->validate([
            'order_number' => 'required',
        ]);

        $order = $this->trackingService->findByInvoiceNumber($request->order_number);

        if (! $order) {
            return responseMessage('Order not found', 404);
        }

        $statusEnum = StatusEnum::from($order->status);

        return responseMessage('Order found', 200, [
            'order_number' => $order->invoice_number,
            'updated_at' => $order->updated_at,
            'status' => [
                'name' => $statusEnum->name,
                'color' => $statusEnum->color(),
            ],
        ]);
    }

    public function productVerify(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        return successResponse(
            $this->trackingService->verifiedProducts($request->phone)
                ->map(fn ($verify) => (new ProductVerifyResource($verify))->resolve($request))
        );
    }
}
