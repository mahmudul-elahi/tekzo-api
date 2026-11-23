<?php

declare(strict_types=1);

namespace App\Models\PreOwned;

use App\Models\Product\Attribute\Attribute;
use App\Models\Product\AttributeValue;
use Illuminate\Database\Eloquent\Model;

class PreOwnedAttribute extends Model
{
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id', 'id');
    }

    public function attributeValue()
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id', 'id');
    }
}
