<?php

namespace App\Models\ContactUs;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('contact_us')]
#[Fillable([
    'name', 'email', 'subject', 'message',
])]
class Contactus extends Model {}
