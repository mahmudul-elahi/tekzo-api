<?php

namespace App\Models\Blog;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'slug',
    'icon',
    'color',
    'description',
    'status',
])]
class BlogCategory extends Model {}
