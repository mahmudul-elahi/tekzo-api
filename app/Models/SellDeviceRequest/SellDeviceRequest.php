<?php

namespace App\Models\SellDeviceRequest;

use App\Enums\StatusEnum;
use App\Models\Brand\Brand;
use App\Models\Category\Category;
use App\Models\Contact\Contact;
use App\Models\NewDistrict\NewDistrict;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'contact_id',
    'ad_title',
    'category_id',
    'brand_id',
    'new_district_id',
    'model',
    'condition',
    'ram',
    'storage',
    'color',
    'is_exchange_possible',
    'asking_price',
    'is_negotiable',
    'video_url',
    'description',
    'phone_number',
    'status',
])]
class SellDeviceRequest extends Model
{
    use SoftDeletes;

    protected $casts = [
        'is_exchange_possible' => 'boolean',
        'is_negotiable' => 'boolean',
        'asking_price' => 'float',
        'status' => StatusEnum::class,
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }

    public function newDistrict(): BelongsTo
    {
        return $this->belongsTo(NewDistrict::class, 'new_district_id', 'id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(SellDeviceRequestImage::class, 'sell_device_request_id', 'id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(SellDeviceRequestFeature::class, 'sell_device_request_id', 'id');
    }
}
