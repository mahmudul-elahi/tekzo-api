<?php

namespace App\Http\Resources\Order;

use App\Enums\StatusEnum;
use App\Models\Order\Order;
use App\Support\OrderPaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Every order attribute, with payment status and status replaced by their display forms.
 *
 * @mixin Order
 */
class RecentOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusEnum = StatusEnum::from($this->status);

        return [
            ...$this->resource->toArray(),
            'payment_status' => OrderPaymentStatus::format($this->resource),
            'status' => [
                'name' => $statusEnum->name,
                'color' => $statusEnum->color(),
            ],
        ];
    }
}
