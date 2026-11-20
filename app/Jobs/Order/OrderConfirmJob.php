<?php

namespace App\Jobs\Order;

use App\Mail\Customer\OrderConfirmMail;
use App\Models\Order\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Milon\Barcode\DNS1D;

class OrderConfirmJob implements ShouldQueue
{
    use Queueable;

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
        $order = $this->order->load(['contact', 'orderLists' => function ($query) {
            $query->with([
                'preOwned.attributes.attributeValue',
                'product',
                'variant.attributeValues'
            ]);
        }]);

        $totalDiscount = $order->orderLists->sum(function ($item) {
            return (float)$item->discount_amount ?? 0;
        });

        $totalUnitPrice = $order->orderLists->sum(function ($item) {
            return ($item->product_price * $item->quantity);
        });

        $totalCareCharge = $order->orderLists->sum(function ($item) {
            $chargeValue = $item->productCare ? ($item->productCare->care?->value ?? 0) : 0;
            return ($item->product_price * $item->quantity) * ($chargeValue / 100);
        });

        $totalPurchasePoint = $order->orderLists->sum(function ($item) {
            $point = $item->product ? ($item->product->purchase_point ?? 0) : 0;
            return ($point * $item->quantity);
        });


        $data = [

            'taka' => public_path('asset/taka.png'),
            'fontBangla' => storage_path('fonts/NotoSansBengali-Regular.ttf'),
            'barcode' => 'data:image/png;base64,' . (new DNS1D)->getBarcodePNG($order->invoice_number, "C128"),
            'totalUnitPrice' => $totalUnitPrice,
            'totalCareCharge' => $totalCareCharge,
            'totalPurchasePoint' => $totalPurchasePoint,
            'order' => $order,
            'customer' => $order->contact,
            'items' => $order->orderLists,
            'logo' => "https://salamat-innovation.s3.ap-south-1.amazonaws.com/575/TEKZO-Logo-1000x500.png",
            'totalDiscount' => $totalDiscount,
        ];

        // PDF generate
        $pdf = Pdf::loadView('order.order_invoice', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        $fileName = 'invoice_' . $order->invoice_number . '.pdf';
        $path = storage_path('app/public/' . $fileName);

        file_put_contents($path, $pdf->output());

        try {
            Mail::to($order->contact?->email)
                ->send(new OrderConfirmMail($order, $path));

            if (file_exists($path)) {
                unlink($path);
            }
        } catch (\Exception $e) {
            // optional: log error
        }
    }
}
