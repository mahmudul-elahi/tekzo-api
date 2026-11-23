<?php

namespace App\Models\SellDeviceRequest;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sell_device_request_id',
    'image',
    'is_primary',
])]
class SellDeviceRequestImage extends Model
{
    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function sellDeviceRequest(): BelongsTo
    {
        return $this->belongsTo(SellDeviceRequest::class, 'sell_device_request_id', 'id');
    }
}
