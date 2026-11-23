<?php

namespace App\Models\SellDeviceRequest;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sell_device_request_id',
    'feature_name',
    'feature_value',
])]
class SellDeviceRequestFeature extends Model
{
    public function sellDeviceRequest(): BelongsTo
    {
        return $this->belongsTo(SellDeviceRequest::class, 'sell_device_request_id', 'id');
    }
}
