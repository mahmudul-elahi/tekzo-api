<?php

declare(strict_types=1);

namespace App\Http\Resources\Order;

use App\Models\Order\OrderPaymentHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderPaymentHistory
 */
class PaymentHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_method' => $this->payment_method,
            'amount' => $this->amount,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
