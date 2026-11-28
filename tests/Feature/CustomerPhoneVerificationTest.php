<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Outside production the phone OTP is always 123456 and no SMS is sent.
 */
class CustomerPhoneVerificationTest extends TestCase
{
    private const string TEST_OTP = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('address')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('type')->default('consumer');
            $table->string('status')->default('active');
            $table->boolean('online')->default(false);
            $table->unsignedBigInteger('total_points')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('elite_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('contact_elite_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('elite_id');
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('code');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_the_profile_shows_an_unverified_phone_number(): void
    {
        $this->actingAsCustomer($this->customer());

        $this->getJson(route('customer.profile.index'))
            ->assertOk()
            ->assertJsonPath('data.is_phone_verified', false)
            ->assertJsonPath('data.phone_verified_at', null);
    }

    public function test_a_customer_can_verify_the_number_they_registered_with(): void
    {
        $customer = $this->customer(phone: '+8801712345678');
        $this->actingAsCustomer($customer);

        $this->postJson(route('customer.profile.phone-verify-request'), ['phone_number' => '01712345678'])
            ->assertOk();
        $this->postJson(route('customer.profile.phone-verify'), ['phone_number' => '01712345678', 'code' => self::TEST_OTP])
            ->assertOk();

        $this->assertSame('+8801712345678', $customer->fresh()->phone);
        $this->getJson(route('customer.profile.index'))
            ->assertOk()
            ->assertJsonPath('data.is_phone_verified', true)
            ->assertJsonPath('data.phone_verified_at', $customer->fresh()->phone_verified_at->toIso8601String());
    }

    public function test_an_already_verified_number_is_not_sent_another_code(): void
    {
        $this->actingAsCustomer($this->customer(phone: '+8801712345678', phoneVerifiedAt: now()));

        $this->postJson(route('customer.profile.phone-verify-request'), ['phone_number' => '01712345678'])
            ->assertStatus(400);

        $this->assertDatabaseCount('verification_codes', 0);
    }

    public function test_a_new_number_is_stored_in_the_canonical_format_once_verified(): void
    {
        $customer = $this->customer(phone: '+8801712345678', phoneVerifiedAt: now());
        $this->actingAsCustomer($customer);

        $this->postJson(route('customer.profile.phone-verify-request'), ['phone_number' => '8801812345678'])
            ->assertOk();
        $this->postJson(route('customer.profile.phone-verify'), ['phone_number' => '01812345678', 'code' => self::TEST_OTP])
            ->assertOk();

        $this->assertSame('+8801812345678', $customer->fresh()->phone);
    }

    private function customer(string $phone = '+8801712345678', mixed $phoneVerifiedAt = null): Contact
    {
        return Contact::query()->create([
            'name' => 'Rahim',
            'email' => 'rahim@example.com',
            'phone' => $phone,
            'password' => 'secret-password',
            'phone_verified_at' => $phoneVerifiedAt,
        ]);
    }

    private function actingAsCustomer(Contact $customer): void
    {
        Sanctum::actingAs($customer, ['*'], 'sanctum');
        Sanctum::actingAs($customer, ['*'], 'customer');
    }
}
