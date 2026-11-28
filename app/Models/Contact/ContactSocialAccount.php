<?php

namespace App\Models\Contact;

use App\Enums\SocialProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $contact_id
 * @property SocialProvider $provider
 * @property string $provider_user_id
 * @property string|null $email
 */
#[Fillable(['contact_id', 'provider', 'provider_user_id', 'email'])]
class ContactSocialAccount extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
