<?php

namespace App\Models\News;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use SoftDeletes;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
