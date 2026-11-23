<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderTrackingRequest;
use App\Http\Requests\Order\ProductVerifyRequest;
use App\Http\Resources\Order\ProductVerifyResource;
use App\Models\Order\Order;
use App\Services\Order\OrderTrackingService;
use Illuminate\Http\JsonResponse;

class OrderTrackController extends Controller
{
    public function __construct(private readonly OrderTrackingService $trackingService) {}

    public function orderTracking(OrderTrackingRequest $request): JsonResponse
    {

        $order = $this->trackingService->findByInvoiceNumber($request->order_number);

        if (! $order instanceof Order) {
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

    public function productVerify(ProductVerifyRequest $request): JsonResponse
    {

        return successResponse(
            $this->trackingService->verifiedProducts($request->phone)
                ->map(fn ($verify) => (new ProductVerifyResource($verify))->resolve($request))
        );
    }
}
