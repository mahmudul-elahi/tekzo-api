<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductSpecification extends Model
{
    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpecificationGroup::class, 'parent_id', 'id');
    }

    public function sub()
    {
        return $this->belongsTo(SpecificationGroup::class, 'sub_id', 'id');
    }
}
