<?php

namespace App\Models\SellDeviceRequest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellDeviceRequestFeature extends Model
{
    protected $fillable = [
        'sell_device_request_id',
        'feature_name',
        'feature_value',
    ];

    public function sellDeviceRequest(): BelongsTo
    {
        return $this->belongsTo(SellDeviceRequest::class, 'sell_device_request_id', 'id');
    }
}
