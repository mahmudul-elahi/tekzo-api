<?php

declare(strict_types=1);

namespace App\Services\Customer\Social;

use App\Enums\SocialProvider;
use App\Exceptions\GeneralException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Throwable;

/**
 * Turns a token the storefront got from Google, Facebook or Apple into a verified identity.
 *
 * Socialite only checks that a token is valid, not which app it was issued to, so Google and
 * Facebook tokens are first checked against this app's client ID. Without that, a token a
 * customer granted to any other app could be replayed here to log in as them. Apple ID tokens
 * are signed JWTs whose audience the Apple provider already checks.
 */
class SocialTokenVerifier
{
    /**
     * @throws GeneralException when the token is invalid, expired or issued to another app
     */
    public function verify(SocialProvider $provider, string $token): SocialIdentity
    {
        if (blank(config("services.{$provider->value}.client_id"))) {
            throw new GeneralException(ucfirst($provider->value).' login is not configured.', 503);
        }

        try {
            match ($provider) {
                SocialProvider::Google => $this->ensureGoogleTokenIsForThisApp($token),
                SocialProvider::Facebook => $this->ensureFacebookTokenIsForThisApp($token),
                SocialProvider::Apple => null,
            };

            $driver = Socialite::driver($provider->value);

            if (! $driver instanceof AbstractProvider) {
                throw new GeneralException(ucfirst($provider->value).' login is not configured.', 503);
            }

            $user = $driver->userFromToken($token);
        } catch (GeneralException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new GeneralException('The '.ucfirst($provider->value).' sign-in could not be verified. Please try again.', 401, $exception);
        }

        return new SocialIdentity(
            provider: $provider,
            id: (string) $user->getId(),
            email: $user->getEmail() ? strtolower($user->getEmail()) : null,
            emailVerified: $user->getEmail() !== null && $this->isEmailVerified($provider, $user->getRaw()),
            name: $user->getName() ?: null,
        );
    }

    /**
     * @throws GeneralException
     * @throws ConnectionException
     */
    private function ensureGoogleTokenIsForThisApp(string $token): void
    {
        $response = Http::connectTimeout(5)->timeout(10)
            ->get('https://oauth2.googleapis.com/tokeninfo', ['access_token' => $token]);

        $clientId = config('services.google.client_id');

        if (! $response->successful() || ! in_array($clientId, [$response->json('aud'), $response->json('azp')], true)) {
            throw new GeneralException('The Google sign-in could not be verified. Please try again.', 401);
        }
    }

    /**
     * @throws GeneralException
     * @throws ConnectionException
     */
    private function ensureFacebookTokenIsForThisApp(string $token): void
    {
        $appId = (string) config('services.facebook.client_id');

        $response = Http::connectTimeout(5)->timeout(10)
            ->get('https://graph.facebook.com/debug_token', [
                'input_token' => $token,
                'access_token' => $appId.'|'.config('services.facebook.client_secret'),
            ]);

        if (! $response->successful() || $response->json('data.is_valid') !== true || (string) $response->json('data.app_id') !== $appId) {
            throw new GeneralException('The Facebook sign-in could not be verified. Please try again.', 401);
        }
    }

    /**
     * Whether the provider confirmed the person owns the email address. Facebook only
     * returns email addresses the person has confirmed.
     *
     * @param  array<string, mixed>  $raw
     */
    private function isEmailVerified(SocialProvider $provider, array $raw): bool
    {
        return match ($provider) {
            SocialProvider::Google => filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN),
            SocialProvider::Apple => filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            SocialProvider::Facebook => true,
        };
    }
}
