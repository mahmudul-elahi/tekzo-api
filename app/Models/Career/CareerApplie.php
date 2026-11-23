<?php

declare(strict_types=1);

namespace App\Models\Career;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Table('career_applies')]
#[Fillable([
    'career_id',
    'name',
    'email',
    'phone',
    'deparment',
    'photo',
    'gender',
    'age',
    'skills',
    'reason_of_join',
    'choos_reason',
    'cv',
    'status',
])]
class CareerApplie extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('candidate_photo')
            ->singleFile();

        $this->addMediaCollection('candidate_cv')
            ->singleFile();
    }
}
