<?php

declare(strict_types=1);

namespace App\Models\Emi;

use Illuminate\Database\Eloquent\Model;

class EmiBank extends Model
{
    protected $casts = ['is_active' => 'boolean'];

    public function tenures()
    {
        return $this->hasMany(EmiTenure::class);
    }
}
