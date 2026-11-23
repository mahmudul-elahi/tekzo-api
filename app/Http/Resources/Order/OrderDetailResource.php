<?php

namespace App\Http\Resources\Order;

use App\Enums\StatusEnum;
use App\Models\Order\Order;
use App\Support\OrderPaymentStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusEnum = StatusEnum::from($this->status);

        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoice_number,
            'grandTotal' => $this->grand_total,
            'subTotal' => $this->sub_total,
            'shippingCharge' => $this->shipping_charge,
            'paymentMethod' => $this->payment_method,
            'paymentStatus' => OrderPaymentStatus::format($this->resource),
            'dueAmount' => $this->due_amount,
            'status' => [
                'name' => $statusEnum->name,
                'color' => $statusEnum->color(),
            ],
            'date' => Carbon::parse($this->updated_at)->format('Y-m-d'),
            'time' => Carbon::parse($this->updated_at)->format('h:i A'),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
