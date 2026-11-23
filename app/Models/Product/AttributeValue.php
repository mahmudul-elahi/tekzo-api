<?php

namespace App\Models\Product;

use App\Models\Product\Attribute\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeValue extends Model
{
    use SoftDeletes;

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
