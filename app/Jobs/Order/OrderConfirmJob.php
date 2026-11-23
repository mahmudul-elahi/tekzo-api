<?php

namespace App\Jobs\Order;

use App\Mail\Customer\OrderConfirmMail;
use App\Models\Order\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Milon\Barcode\DNS1D;
use Throwable;

class OrderConfirmJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    /**
     * Create a new job instance.
     */
    public function __construct(protected Order $order)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Everything the invoice view reads, so it renders without lazy loading.
        $order = $this->order->load(['contact', 'shippingAddress', 'area', 'orderLists' => function ($query) {
            $query->with([
                'preOwned.attributes.attributeValue',
                'product',
                'variant.attributeValues.attribute',
                'giftProductVariant' => function ($query) {
                    $query->with(
                        'product:id,product_name,product_image',
                        'productVariant.attributeValues',
                    );
                },
                'productCare.care',
                'preOwnedCare.care',
            ])
                ->withCount('giftProductVariant as total_gift_quantity');
        }]);

        $totalDiscount = $order->orderLists->sum(function ($item) {
            return (float) ($item->discount_amount ?? 0);
        });

        $totalUnitPrice = $order->orderLists->sum(function ($item) {
            return $item->product_price * $item->quantity;
        });

        $totalCareCharge = $order->orderLists->sum(function ($item) {
            $chargeValue = $item->productCare ? ($item->productCare->care?->value ?? 0) : 0;

            return ($item->product_price * $item->quantity) * ($chargeValue / 100);
        });

        $totalPurchasePoint = $order->orderLists->sum(function ($item) {
            $point = $item->product ? ($item->product->purchase_point ?? 0) : 0;

            return $point * $item->quantity;
        });

        $data = [
            'taka' => public_path('asset/taka.png'),
            'fontBangla' => storage_path('fonts/NotoSansBengali-Regular.ttf'),
            'barcode' => 'data:image/png;base64,'.(new DNS1D)->getBarcodePNG($order->invoice_number, 'C128'),
            'totalUnitPrice' => $totalUnitPrice,
            'totalCareCharge' => $totalCareCharge,
            'totalPurchasePoint' => $totalPurchasePoint,
            'order' => $order,
            'customer' => $order->contact,
            'items' => $order->orderLists,
            'logo' => config('services.invoice.confirm_logo_url', 'https://salamat-innovation.s3.ap-south-1.amazonaws.com/575/TEKZO-Logo-1000x500.png'),
            'totalDiscount' => $totalDiscount,
        ];

        // PDF generate
        $pdf = Pdf::loadView('order.order_invoice', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $pdfData = $pdf->output();

        try {
            Mail::to($order->contact?->email)
                ->send(new OrderConfirmMail($order, null, $pdfData));
        } catch (Throwable $e) {
            Log::error('OrderConfirmJob failed to send invoice mail', [
                'order_id' => $order->id,
                'email' => $order->contact?->email,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
