<?php

declare(strict_types=1);

namespace App\Models\Seo;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('seo')]
class Seo extends Model
{
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
