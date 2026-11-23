<?php

declare(strict_types=1);

namespace App\Models\Elite;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'status_name',
    'tagline',
    'logo',
    'banner',
    'benefits',
    'point',
])]
class Elite extends Model
{
    use SoftDeletes;

    protected $casts = [
        'benefits' => 'array',
    ];
}
