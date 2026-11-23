<?php

declare(strict_types=1);

namespace App\Models\Care;

use App\Models\PreOwned\PreOwned;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('pre_owned_cares')]
class PreOwnedCare extends Model
{
    use SoftDeletes;

    public function preOwned()
    {
        return $this->belongsTo(PreOwned::class, 'pre_owned_id', 'id');
    }

    public function care()
    {
        return $this->belongsTo(Care::class, 'care_id', 'id');
    }
}
