<?php

namespace App\Models\Seo;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;


class Seo extends Model
{
    public $table = 'seo';
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
