<?php

namespace App\Models\Point;

use App\Models\Contact\Contact;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    protected $fillable = [
        'contact_id',
        'order_id',
        'type',
        'points',
        'balance_after',
        'note',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
