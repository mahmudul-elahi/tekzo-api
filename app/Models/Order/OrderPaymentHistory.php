<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderPaymentHistory extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'transaction_id',
        'status',
        'gateway_response',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'amount' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
