<?php

namespace App\Models\Order;

use App\Models\Address\CustomerAddress;
use App\Models\Area\Area;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Contact\Contact;
use App\Models\NewDistrict\NewDistrict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'contact_id',
        'tenant_id',
        'payment_method',
        'shipping_address_id',
        'payment_type',
        'paymentId',
        'transaction_id',
        'payment_status',
        'area_id',
        'new_district_id',
        'address',
        'total_booking_amount',
        'sub_total',
        'discount_total',
        'payable_amount',
        'due_amount',
        'shipping_charge',
        'coupon_code',
        'discount_amount',
        'grand_total',
        'invoice_number',
        'customer_note',
        'note',
        'status',
        'role',
        'custom_value',
        'additional_value',
    ];

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if (empty($order->invoice_number)) {
                $order->invoice_number = 'ORD-'.date('Ymd').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'total_booking_amount' => 'decimal:2',
        ];
    }

    public function orderLists(): HasMany
    {
        return $this->hasMany(OrderList::class, 'order_id', 'id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id', 'id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(NewDistrict::class, 'new_district_id', 'id');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id', 'id');
    }

    public function getFullAddress()
    {
        $addressParts = [];

        if ($this->shipping_address_id) {
            return $this->shippingAddress?->getFullAddess() ?? '';
        }
        if ($this->district?->name) {
            $addressParts[] = $this->district->name;
        }
        if ($this->area?->name) {
            $addressParts[] = $this->area->name;
        }
        if ($this->address) {
            $addressParts[] = $this->address;
        }

        return implode(', ', $addressParts);
    }
}
