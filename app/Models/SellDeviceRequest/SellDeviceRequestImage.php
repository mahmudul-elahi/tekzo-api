<?php

namespace App\Models\SellDeviceRequest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellDeviceRequestImage extends Model
{
    protected $fillable = [
        'sell_device_request_id',
        'image',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function sellDeviceRequest(): BelongsTo
    {
        return $this->belongsTo(SellDeviceRequest::class, 'sell_device_request_id', 'id');
    }
}
