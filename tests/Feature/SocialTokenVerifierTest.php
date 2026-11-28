<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SocialProvider;
use App\Exceptions\GeneralException;
use App\Services\Customer\Social\SocialTokenVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A provider token must have been issued to this app; one granted to any other app is refused
 * before the profile is fetched.
 */
class SocialTokenVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'tekzo-google-client',
            'services.facebook.client_id' => '1234567890',
            'services.facebook.client_secret' => 'facebook-secret',
        ]);
    }

    public function test_a_google_token_issued_to_another_app_is_refused(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['aud' => 'other-app', 'azp' => 'other-app'])]);

        $this->expectExceptionObject(new GeneralException('The Google sign-in could not be verified. Please try again.', 401));

        (new SocialTokenVerifier)->verify(SocialProvider::Google, 'token-for-another-app');
    }

    public function test_an_invalid_google_token_is_refused(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_token'], 400)]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionCode(401);

        (new SocialTokenVerifier)->verify(SocialProvider::Google, 'expired-token');
    }

    public function test_a_facebook_token_issued_to_another_app_is_refused(): void
    {
        Http::fake(['graph.facebook.com/debug_token*' => Http::response(['data' => ['is_valid' => true, 'app_id' => '999']])]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionCode(401);

        (new SocialTokenVerifier)->verify(SocialProvider::Facebook, 'token-for-another-app');
    }

    public function test_a_provider_without_credentials_is_reported_as_not_configured(): void
    {
        config(['services.apple.client_id' => null]);
        Http::fake();

        $this->expectException(GeneralException::class);
        $this->expectExceptionCode(503);

        try {
            (new SocialTokenVerifier)->verify(SocialProvider::Apple, 'apple-id-token');
        } finally {
            Http::assertNothingSent();
        }
    }
}
