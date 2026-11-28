<?php

declare(strict_types=1);

namespace App\Services\Customer\Social;

use App\Enums\SocialProvider;
use App\Exceptions\GeneralException;
use App\Models\Contact\Contact;
use App\Models\Contact\ContactSocialAccount;
use App\Repositories\Customer\ContactRepository;
use App\Repositories\Customer\SocialAccountRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Logging in with, connecting and disconnecting Google, Facebook and Apple accounts.
 */
class SocialAuthService
{
    public function __construct(
        private readonly SocialTokenVerifier $verifier,
        private readonly SocialAccountRepository $accounts,
        private readonly ContactRepository $contacts,
    ) {}

    /**
     * Log in with a provider token, and return a Sanctum token.
     *
     * A provider account that is already connected logs in its customer. Otherwise it is
     * connected to the registered customer with the same email, but only when the provider
     * confirmed that email; a new customer is created when no one has it.
     *
     * @param  string|null  $name  the name the app collected, for Apple, which sends it only once
     *
     * @throws GeneralException when the token cannot be verified, or the email belongs to a
     *                          customer but the provider did not confirm it
     */
    public function login(SocialProvider $provider, string $token, ?string $name = null): string
    {
        $identity = $this->verifier->verify($provider, $token);

        $customer = DB::transaction(function () use ($identity, $name): Contact {
            $account = $this->accounts->findByProviderUser($identity->provider, $identity->id);

            if ($account instanceof ContactSocialAccount) {
                $customer = $this->contacts->query()->find($account->contact_id);

                if ($customer instanceof Contact) {
                    return $customer;
                }
            }

            $customer = $this->findRegisteredCustomerByEmail($identity);

            if (! $customer instanceof Contact) {
                $customer = $this->createCustomer($identity, $name);
            }

            $this->accounts->link($customer, $identity->provider, $identity->id, $identity->email);

            return $customer;
        });

        $customer->update(['last_login_at' => now()]);

        return $customer->createToken("customer-{$customer->id}-token")->plainTextToken;
    }

    /**
     * Connect a provider account to the signed-in customer, so they can log in with it.
     *
     * @throws GeneralException when the token cannot be verified, or the provider account is
     *                          already connected to someone else
     */
    public function connect(Contact $customer, SocialProvider $provider, string $token): void
    {
        $identity = $this->verifier->verify($provider, $token);
        $account = $this->accounts->findByProviderUser($identity->provider, $identity->id);

        if ($account instanceof ContactSocialAccount) {
            if ($account->contact_id === $customer->id) {
                return;
            }

            throw new GeneralException('This '.ucfirst($provider->value).' account is already connected to another customer.', 409);
        }

        if ($this->accounts->findForContact($customer, $provider) instanceof ContactSocialAccount) {
            throw new GeneralException('Disconnect your current '.ucfirst($provider->value).' account before connecting another one.', 409);
        }

        $this->accounts->link($customer, $identity->provider, $identity->id, $identity->email);
    }

    public function disconnect(Contact $customer, SocialProvider $provider): void
    {
        $this->accounts->findForContact($customer, $provider)?->delete();
    }

    /**
     * @throws GeneralException when a registered customer has the email but the provider did not confirm it
     */
    private function findRegisteredCustomerByEmail(SocialIdentity $identity): ?Contact
    {
        if ($identity->email === null) {
            return null;
        }

        $customer = $this->contacts->findRegisteredByEmailOrPhone($identity->email)->first();

        if ($customer instanceof Contact && ! $identity->emailVerified) {
            throw new GeneralException(
                'An account already uses this email. Log in with your password, then connect '.ucfirst($identity->provider->value).' from your profile.',
                409,
            );
        }

        return $customer;
    }

    /**
     * A new customer who logs in only through the provider. They get an unguessable password
     * so they are not mistaken for a guest-checkout contact, and can set a real one with
     * "forgot password".
     */
    private function createCustomer(SocialIdentity $identity, ?string $name): Contact
    {
        return $this->contacts->create([
            'name' => $identity->name ?? $name ?? 'Customer',
            'email' => $identity->email,
            'email_verified_at' => $identity->emailVerified ? now() : null,
            'password' => Hash::make(Str::random(64)),
            'status' => 'active',
            'type' => 'consumer',
            'online' => 1,
        ]);
    }
}
