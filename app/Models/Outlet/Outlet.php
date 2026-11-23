<?php

namespace App\Models\Outlet;

use App\Models\Country\Country;
use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    public function images()
    {
        return $this->hasMany(OutletImage::class, 'outlet_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }
}
