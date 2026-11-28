<?php

namespace App\Models\Contact;

use App\Models\Elite\ContactEliteHistory;
use App\Models\Elite\Elite;
use App\Models\Point\PointTransaction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'name',
    'email',
    'phone',
    'secondary_phone',
    'address',
    'password',
    'status',
    'type',
    'email_verified_at',
    'phone_verified_at',
    'last_login_at',
    'gender',
    'date_of_birth',
    'profile_picture',
    'online',
    'total_points',
    'elite_id',
])]
#[Hidden(['password'])]
class Contact extends Authenticatable implements HasMedia
{
    use HasApiTokens, InteractsWithMedia, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'online' => 'boolean',
            'total_points' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function elite()
    {
        return $this->belongsTo(Elite::class);
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function eliteHistories()
    {
        return $this->hasMany(ContactEliteHistory::class);
    }

    public function getCustomerProfilePictureAttribute(): ?string
    {
        $profilePicture = $this->getFirstMedia('customer_profile_picture');

        return $profilePicture?->getFullUrl();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('customer_profile_picture')
            ->singleFile();
    }
}
