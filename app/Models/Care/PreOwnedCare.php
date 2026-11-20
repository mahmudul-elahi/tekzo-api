<?php

namespace App\Models\Care;

use App\Models\PreOwned\PreOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreOwnedCare extends Model
{
    use SoftDeletes;

    protected $table = 'pre_owned_cares';

    public function preOwned()
    {
        return $this->belongsTo(PreOwned::class, 'pre_owned_id', 'id');
    }

    public function care()
    {
        return $this->belongsTo(Care::class, 'care_id', 'id');
    }
}
