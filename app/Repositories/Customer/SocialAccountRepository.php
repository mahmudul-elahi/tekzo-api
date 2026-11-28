<?php

namespace App\Repositories\Customer;

use App\Enums\SocialProvider;
use App\Models\Contact\Contact;
use App\Models\Contact\ContactSocialAccount;
use App\Repositories\BaseRepository;

class SocialAccountRepository extends BaseRepository
{
    public function __construct(ContactSocialAccount $account)
    {
        $this->model = $account;
    }

    public function findByProviderUser(SocialProvider $provider, string $providerUserId): ?ContactSocialAccount
    {
        return ContactSocialAccount::query()
            ->where('provider', $provider->value)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }

    public function findForContact(Contact $contact, SocialProvider $provider): ?ContactSocialAccount
    {
        return ContactSocialAccount::query()
            ->whereBelongsTo($contact)
            ->where('provider', $provider->value)
            ->first();
    }

    public function link(Contact $contact, SocialProvider $provider, string $providerUserId, ?string $email): ContactSocialAccount
    {
        return ContactSocialAccount::query()->create([
            'contact_id' => $contact->id,
            'provider' => $provider->value,
            'provider_user_id' => $providerUserId,
            'email' => $email,
        ]);
    }
}
