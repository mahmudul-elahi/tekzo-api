<?php

namespace App\Models\Page;

use App\Models\Seo\Seo;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'slug',
    'description',
    'tags',
    'primary_logo',
    'secondary_logo',
    'shop_banner',
    'info_banner',
])]
class Page extends Model
{
    use SoftDeletes;

    public function seo()
    {
        return $this->morphOne(Seo::class, 'model');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
