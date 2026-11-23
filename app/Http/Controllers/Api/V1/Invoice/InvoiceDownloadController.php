<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Services\Invoice\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceDownloadController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * The invoice PDF, inline with `preview`, otherwise as a download.
     */
    public function invoiceDownload(Request $request): JsonResponse|Response
    {
        $invoiceNumber = $request->query('invoice_number');

        if (! $invoiceNumber) {
            return responseMessage(message: 'Invoice number is required', code: 400);
        }

        $order = $this->invoiceService->findOrder($invoiceNumber);

        if (! $order instanceof Order) {
            return responseMessage(message: 'Order not found for the provided invoice number', code: 404);
        }

        $pdf = $this->invoiceService->pdf($order);

        if ($request->has('preview')) {
            return response()->make($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$order->invoice_number.'.pdf"',
            ]);
        }

        return response()->make($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$order->invoice_number.'.pdf"',
        ]);
    }

    public function recentOrder(Request $request): JsonResponse
    {
        $invoiceNumber = $request->query('invoice_number');

        if (! $invoiceNumber) {
            return responseMessage(message: 'Invoice number is required', code: 400);
        }

        $order = $this->invoiceService->findOrder($invoiceNumber);

        if (! $order instanceof Order) {
            return responseMessage(message: 'Order not found for the provided invoice number', code: 404);
        }

        return successResponse($this->invoiceService->summary($order));
    }
}
