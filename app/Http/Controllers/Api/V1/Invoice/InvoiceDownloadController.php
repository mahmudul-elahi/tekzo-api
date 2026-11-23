<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Milon\Barcode\DNS1D;

class InvoiceDownloadController extends Controller
{
    public function invoiceDownload()
    {
        $invoiceNumber = request()->query('invoice_number');
        if (! $invoiceNumber) {
            return responseMessage(message: 'Invoice number is required', code: 400);
        }

        $order = Order::query()
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if (! $order) {
            return responseMessage(message: 'Order not found for the provided invoice number', code: 404);
        }

        $pdf = $this->generatePdf($order);

        if (request()->has('preview')) {
            return response()->make($pdf->stream(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$order->invoice_number.'.pdf"',
            ]);
        }

        return response()->make($pdf->download($order->invoice_number.'.pdf'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$order->invoice_number.'.pdf"',
        ]);
    }

    protected function generatePdf($order)
    {
        $order->load([
            'contact:id,name,phone,email',
            'shippingAddress',
            'area',
            'orderLists' => function ($query) {
                $query->with([
                    'preOwned.attributes.attributeValue',
                    'product:id,product_name,product_image,warranty_day,purchase_point',
                    'variant.attributeValues',
                    'variant.attributeValues.attribute',
                    'giftProductVariant' => function ($query) {
                        $query->with(
                            'product:id,product_name,product_image',
                            'productVariant.attributeValues',
                        );
                    },
                    'productCare' => function ($query) {
                        $query->with('care:id,name,value');
                    },
                    'preOwnedCare' => function ($query) {
                        $query->with('care:id,name,value');
                    },

                ])
                    ->withCount('giftProductVariant as total_gift_quantity');
            },
        ]);

        $totalDiscount = $order->orderLists->sum(function ($item) {
            return (float) $item->discount_amount * $item->quantity ?? 0;
        });

        $totalUnitPrice = $order->orderLists->sum(function ($item) {
            return $item->product_price * $item->quantity;
        });

        $totalCareCharge = $order->orderLists->sum(function ($item) {
            $chargeValue = $item->productCare ? ($item->productCare->care?->value ?? 0) : 0;

            return ($item->product_price * $item->quantity) * ($chargeValue / 100);
        });

        $totalPreOwnedCareCharge = $order->orderLists->sum(function ($item) {
            $chargeValue = $item->preOwnedCare ? ($item->preOwnedCare->care?->value ?? 0) : 0;

            return ($item->product_price * $item->quantity) * ($chargeValue / 100);
        });

        $totalPurchasePoint = $order->orderLists->sum(function ($item) {
            return $item->purchase_point ?? 0;
        });

        $data = [
            'taka' => public_path('asset/taka.png'),
            'fontBangla' => storage_path('fonts/NotoSansBengali-Regular.ttf'),
            'barcode' => 'data:image/png;base64,'.(new DNS1D)->getBarcodePNG($order->invoice_number, 'C128'),
            'totalUnitPrice' => $totalUnitPrice,
            'totalCareCharge' => $totalCareCharge + $totalPreOwnedCareCharge,
            'totalPurchasePoint' => $totalPurchasePoint,
            'order' => $order,
            'customer' => $order->contact,
            'items' => $order->orderLists,
            'logo' => config('services.invoice.download_logo_url', 'https://d2c2zgda4zwbg9.cloudfront.net/481/TEKZO-Logo-1000x1000.png'),
            'totalDiscount' => $totalDiscount,
        ];

        return Pdf::loadView('order.order_invoice', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }

    public function recentOrder()
    {
        $invoiceNumber = request()->query('invoice_number');

        if (! $invoiceNumber) {
            return responseMessage(message: 'Invoice number is required', code: 400);
        }

        $order = Order::query()
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if (! $order) {
            return responseMessage(message: 'Order not found for the provided invoice number', code: 404);
        }

        $orderSummary = $order->load([
            'contact:id,name,phone',
            'area',
            'district',
            'shippingAddress' => function ($query) {
                $query->with(['area', 'district', 'contact:id,name,phone']);
            },
            'orderLists' => function ($query) {
                $query->with(['product' => function ($query) {
                    $query->select('id', 'product_name', 'product_image', 'brand_id', 'category_id')
                        ->with(['brand:id,name', 'category' => function ($query) {
                            $query->with(['parent:id,name']);
                        }]);
                }, 'variant.attributeValues']);
            },
        ]);

        $orderCount = Order::query()
            ->where('contact_id', $order->contact_id)
            ->count();

        return successResponse([
            ...$orderSummary->toArray(),
            'order_count' => $orderCount,
        ]);
    }
}
