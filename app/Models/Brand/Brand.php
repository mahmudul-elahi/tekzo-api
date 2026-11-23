<?php

namespace App\Models\Brand;

use App\Models\Product\LongDescription;
use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Brand extends Model
{
    use Searchable, SoftDeletes;

    public $casts = [
        'status' => 'boolean',
    ];

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id', 'id');
    }

    public function longDescription()
    {
        return $this->hasOne(LongDescription::class, 'model_id')
            ->where('model_type', 'App\Models\Landlord\Brand\Brand')
            ->whereHas('tenant', function ($query) {
                $query->where('slug', config('services.tenant_slug'));
            });
    }
}
