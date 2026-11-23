<?php

declare(strict_types=1);

namespace App\Models\NewDistrict;

use App\Models\Area\Area;
use Illuminate\Database\Eloquent\Model;

class NewDistrict extends Model
{
    public function area()
    {
        return $this->hasMany(Area::class, 'new_district_id');
    }
}
