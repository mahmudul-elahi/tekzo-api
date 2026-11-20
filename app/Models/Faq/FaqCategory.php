<?php

namespace App\Models\Faq;

use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Model;

class FaqCategory extends Model
{

    public function faqs()
    {
        return $this->hasMany(Faq::class, 'faq_category_id', 'id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
