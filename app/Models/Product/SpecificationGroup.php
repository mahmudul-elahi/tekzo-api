<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class SpecificationGroup extends Model
{
    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpecificationGroup::class, 'parent_id');
    }

    public function subSpecificationGroups()
    {
        return $this->hasMany(SpecificationGroup::class, 'parent_id', 'id');
    }
}
