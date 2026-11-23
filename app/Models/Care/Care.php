<?php

declare(strict_types=1);

namespace App\Models\Care;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Care extends Model
{
    use SoftDeletes;

    public $casts = [
        'value' => 'float',
    ];
}
