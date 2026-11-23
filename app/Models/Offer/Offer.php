<?php

declare(strict_types=1);

namespace App\Models\Offer;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    public function offerProducts()
    {
        return $this->hasMany(OfferProduct::class, 'offer_id', 'id');
    }
}
