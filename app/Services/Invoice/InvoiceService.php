<?php

namespace App\Services\Invoice;

use App\Models\Order\Order;
use App\Repositories\Order\OrderRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Milon\Barcode\DNS1D;

class InvoiceService
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function findOrder(mixed $invoiceNumber): ?Order
    {
        return $this->orders->findByInvoiceNumber($invoiceNumber);
    }

    /**
     * The A4 invoice PDF, with line totals, care charges, points and a barcode of the invoice number.
     */
    public function pdf(Order $order): DomPdf
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

        $totalDiscount = $order->orderLists->sum(fn ($item) => (float) $item->discount_amount * $item->quantity ?? 0);

        $totalUnitPrice = $order->orderLists->sum(fn ($item) => $item->product_price * $item->quantity);

        $totalCareCharge = $order->orderLists->sum(function ($item) {
            $chargeValue = $item->productCare ? ($item->productCare->care?->value ?? 0) : 0;

            return ($item->product_price * $item->quantity) * ($chargeValue / 100);
        });

        $totalPreOwnedCareCharge = $order->orderLists->sum(function ($item) {
            $chargeValue = $item->preOwnedCare ? ($item->preOwnedCare->care?->value ?? 0) : 0;

            return ($item->product_price * $item->quantity) * ($chargeValue / 100);
        });

        $totalPurchasePoint = $order->orderLists->sum(fn ($item) => $item->purchase_point ?? 0);

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

    /**
     * The order with its customer, addresses and lines, plus how many orders the customer has placed.
     *
     * @return array<string, mixed>
     */
    public function summary(Order $order): array
    {
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

        return [
            ...$orderSummary->toArray(),
            'order_count' => $this->orders->countForContact($order->contact_id),
        ];
    }
}
