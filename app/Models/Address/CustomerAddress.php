<?php

namespace App\Models\Address;

use App\Models\Area\Area;
use App\Models\Contact\Contact;
use App\Models\Customer\Customer;
use App\Models\NewDistrict\NewDistrict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'contact_id',
        'new_district_id',
        'area_id',
        'street',
        'label',
        'is_default_shipping',
        'is_default_billing',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(NewDistrict::class, 'new_district_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function getFullAddess()
    {
        $addressParts = [];

        if ($this->district?->name) {
            $addressParts[] = $this->district->name;
        }
        if ($this->area?->name) {
            $addressParts[] = $this->area->name;
        }
        if ($this->street) {
            $addressParts[] = $this->street;
        }

        return implode(', ', $addressParts);
    }
}
