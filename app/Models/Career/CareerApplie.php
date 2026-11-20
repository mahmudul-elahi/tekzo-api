<?php

namespace App\Models\Career;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CareerApplie extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'career_applies';

    protected $fillable = [
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
        'status'
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('candidate_photo')
            ->singleFile();

        $this->addMediaCollection('candidate_cv')
            ->singleFile();
    }
}
