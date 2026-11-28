<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SocialProvider;
use App\Models\Contact\Contact;
use App\Services\Customer\Social\SocialIdentity;
use App\Services\Customer\Social\SocialTokenVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('type')->default('consumer');
            $table->string('status')->default('active');
            $table->boolean('online')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contact_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->string('provider', 20);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['contact_id', 'provider']);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_a_new_person_gets_an_account_when_they_first_log_in(): void
    {
        $this->providerSays(new SocialIdentity(SocialProvider::Google, 'google-1', 'karim@gmail.com', true, 'Karim Uddin'));

        $this->postJson(route('auth.social-login', 'google'), ['token' => 'google-token'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $customer = Contact::query()->where('email', 'karim@gmail.com')->sole();
        $this->assertSame('Karim Uddin', $customer->name);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertNotNull($customer->password, 'A social customer must not look like a guest-checkout contact.');
        $this->assertDatabaseHas('contact_social_accounts', ['contact_id' => $customer->id, 'provider' => 'google', 'provider_user_id' => 'google-1']);
    }

    public function test_a_connected_account_logs_in_its_customer_again(): void
    {
        $customer = $this->registeredCustomer('rahim@example.com');
        DB::table('contact_social_accounts')->insert(['contact_id' => $customer->id, 'provider' => 'facebook', 'provider_user_id' => 'fb-1']);
        $this->providerSays(new SocialIdentity(SocialProvider::Facebook, 'fb-1', 'other@example.com', true, 'Rahim'));

        $this->postJson(route('auth.social-login', 'facebook'), ['token' => 'facebook-token'])->assertOk();

        $this->assertSame(1, $customer->tokens()->count());
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_a_verified_email_connects_to_the_registered_customer_with_that_email(): void
    {
        $customer = $this->registeredCustomer('rahim@example.com');
        $this->providerSays(new SocialIdentity(SocialProvider::Google, 'google-1', 'rahim@example.com', true, 'Rahim'));

        $this->postJson(route('auth.social-login', 'google'), ['token' => 'google-token'])->assertOk();

        $this->assertSame(1, $customer->tokens()->count());
        $this->assertDatabaseHas('contact_social_accounts', ['contact_id' => $customer->id, 'provider' => 'google']);
    }

    public function test_an_unverified_email_cannot_take_over_a_registered_customer(): void
    {
        $customer = $this->registeredCustomer('rahim@example.com');
        $this->providerSays(new SocialIdentity(SocialProvider::Apple, 'apple-1', 'rahim@example.com', false, null));

        $this->postJson(route('auth.social-login', 'apple'), ['token' => 'apple-id-token'])
            ->assertStatus(409);

        $this->assertSame(0, $customer->tokens()->count());
        $this->assertDatabaseCount('contact_social_accounts', 0);
    }

    public function test_apple_uses_the_name_the_app_sends_when_the_token_has_none(): void
    {
        $this->providerSays(new SocialIdentity(SocialProvider::Apple, 'apple-1', 'karim@privaterelay.appleid.com', true, null));

        $this->postJson(route('auth.social-login', 'apple'), ['token' => 'apple-id-token', 'name' => 'Karim Uddin'])
            ->assertOk();

        $this->assertDatabaseHas('contacts', ['name' => 'Karim Uddin']);
    }

    public function test_an_unknown_provider_is_not_found(): void
    {
        $this->postJson('/api/v1/auth/social/twitter', ['token' => 'token'])->assertNotFound();
    }

    public function test_a_customer_can_connect_and_disconnect_a_provider_from_their_profile(): void
    {
        $customer = $this->signedIn($this->registeredCustomer('rahim@example.com'));
        $this->providerSays(new SocialIdentity(SocialProvider::Google, 'google-1', 'rahim.personal@gmail.com', true, 'Rahim'));

        $this->postJson(route('customer.social-accounts.store', 'google'), ['token' => 'google-token'])->assertOk();

        $this->getJson(route('customer.social-accounts.index'))
            ->assertOk()
            ->assertJsonPath('data.0.provider', 'google')
            ->assertJsonPath('data.0.connected', true)
            ->assertJsonPath('data.0.email', 'rahim.personal@gmail.com')
            ->assertJsonPath('data.1.connected', false);

        $this->deleteJson(route('customer.social-accounts.destroy', 'google'))->assertOk();

        $this->assertDatabaseMissing('contact_social_accounts', ['contact_id' => $customer->id]);
    }

    public function test_a_provider_account_cannot_be_connected_to_two_customers(): void
    {
        $owner = $this->registeredCustomer('owner@example.com');
        DB::table('contact_social_accounts')->insert(['contact_id' => $owner->id, 'provider' => 'google', 'provider_user_id' => 'google-1']);
        $this->signedIn($this->registeredCustomer('rahim@example.com'));
        $this->providerSays(new SocialIdentity(SocialProvider::Google, 'google-1', 'owner@gmail.com', true, 'Owner'));

        $this->postJson(route('customer.social-accounts.store', 'google'), ['token' => 'google-token'])
            ->assertStatus(409);

        $this->assertDatabaseCount('contact_social_accounts', 1);
    }

    private function providerSays(SocialIdentity $identity): void
    {
        $this->app->instance(SocialTokenVerifier::class, new class($identity) extends SocialTokenVerifier
        {
            public function __construct(private readonly SocialIdentity $identity) {}

            public function verify(SocialProvider $provider, string $token): SocialIdentity
            {
                return $this->identity;
            }
        });
    }

    private function registeredCustomer(string $email): Contact
    {
        return Contact::query()->create(['name' => 'Rahim', 'email' => $email, 'password' => Hash::make('secret-password')]);
    }

    private function signedIn(Contact $customer): Contact
    {
        Sanctum::actingAs($customer, ['*'], 'sanctum');
        Sanctum::actingAs($customer, ['*'], 'customer');

        return $customer;
    }
}
