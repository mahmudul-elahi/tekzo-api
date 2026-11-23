<?php

declare(strict_types=1);

namespace App\Models\Elite;

use App\Models\Contact\Contact;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(timestamps: false)]
#[Fillable(['contact_id', 'elite_id', 'achieved_at'])]
class ContactEliteHistory extends Model
{
    protected $casts = ['achieved_at' => 'datetime'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function elite()
    {
        return $this->belongsTo(Elite::class);
    }
}
