<?php

declare(strict_types=1);

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'order_id',
    'payment_method',
    'amount',
    'transaction_id',
    'status',
    'gateway_response',
])]
class OrderPaymentHistory extends Model
{
    protected $casts = [
        'gateway_response' => 'array',
        'amount' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
