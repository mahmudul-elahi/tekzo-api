<?php

namespace App\Models\ContactUs;

use Illuminate\Database\Eloquent\Model;

class Contactus extends Model
{
    public $table = 'contact_us';

    protected $fillable = [
        'name', 'email', 'subject', 'message'
    ];
}
