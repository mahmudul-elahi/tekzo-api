<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\ProductVerify\ProductVerify;
use Illuminate\Http\Request;

class OrderTrackController extends Controller
{
    public function orderTracking(Request $request)
    {
        $request->validate([
            'order_number' => 'required',
        ]);

        $order = Order::query()
            ->where('invoice_number', $request->order_number)
            ->first();

        if (!$order) {
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


    public function productVerify(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $verifyProducts = ProductVerify::query()
            ->select('id', 'phone', 'name', 'barcode_number', 'product_id', 'created_at', 'status', 'claimed')
            ->where('phone', $request->phone)
            ->with(['product:id,product_name,product_image'])
            ->get()
            ->map(function ($verify) {;
                return [
                    'id' => $verify->id,
                    'phone' => $verify->phone,
                    'name' => $verify->name,
                    'barcode_number' => $verify->barcode_number,
                    'product_id' => $verify->product_id,
                    'created_at' => $verify->created_at,
                    'status' => $verify->status,
                    'claimed' => $verify->claimed,
                    'product' => $verify->product ? [
                        'id' => $verify->product->id,
                        'product_name' => $verify->product->product_name,
                        'product_image' => $verify->product->product_image,
                    ] : null,
                ];
            });


        return successResponse($verifyProducts);
    }
}
