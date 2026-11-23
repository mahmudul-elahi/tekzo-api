<?php

namespace App\Http\Resources\Order;

use App\Enums\StatusEnum;
use App\Models\Order\Order;
use App\Support\OrderPaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order in the customer's order list.
 *
 * @mixin Order
 */
class CustomerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusEnum = StatusEnum::from($this->status);

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'payment_method' => $this->payment_method,
            'payment_status' => OrderPaymentStatus::format($this->resource),
            'shipping_charge' => $this->shipping_charge ?? 0,
            'sub_total' => $this->sub_total,
            'grand_total' => $this->grand_total,
            'due_amount' => $this->due_amount,
            'payable_amount' => $this->payable_amount,
            'status' => [
                'name' => $statusEnum->name,
                'color' => $statusEnum->color(),
            ],
            'total_quantity' => $this->order_lists_sum_quantity ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
