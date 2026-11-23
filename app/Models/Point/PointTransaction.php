<?php

declare(strict_types=1);

namespace App\Models\Point;

use App\Models\Contact\Contact;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'contact_id',
    'order_id',
    'type',
    'points',
    'balance_after',
    'note',
])]
class PointTransaction extends Model
{
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
