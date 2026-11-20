<?php

namespace App\Models\Offer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Offer extends Model
{
    public function offerProducts()
    {
        return $this->hasMany(OfferProduct::class, 'offer_id', 'id');
    }
}
