<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerLoginTest extends TestCase
{
    private const string PASSWORD = 'secret-password';

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
            $table->unsignedBigInteger('total_points')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('elite_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
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

    public function test_a_customer_can_log_in_with_their_email(): void
    {
        $customer = $this->registeredCustomer(email: 'rahim@example.com');

        $this->postJson(route('auth.login'), ['login' => 'rahim@example.com', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotNull($customer->fresh()->last_login_at);
    }

    public function test_clients_sending_the_old_email_field_can_still_log_in(): void
    {
        $this->registeredCustomer(email: 'rahim@example.com');

        $this->postJson(route('auth.login'), ['email' => 'rahim@example.com', 'password' => self::PASSWORD])
            ->assertOk();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function phoneFormats(): array
    {
        return [
            'local number, stored canonically' => ['01712345678', '+8801712345678'],
            'with spaces and dashes' => ['017-1234 5678', '+8801712345678'],
            'international number' => ['+8801712345678', '+8801712345678'],
            'stored in the old local format' => ['+8801712345678', '01712345678'],
            'stored without the plus sign' => ['01712345678', '8801712345678'],
        ];
    }

    #[DataProvider('phoneFormats')]
    public function test_a_customer_can_log_in_with_their_mobile_number(string $typedPhone, string $storedPhone): void
    {
        $this->registeredCustomer(phone: $storedPhone);

        $this->postJson(route('auth.login'), ['login' => $typedPhone, 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'token_type']]);
    }

    public function test_the_customer_whose_password_matches_is_logged_in_when_a_number_is_shared(): void
    {
        $otherCustomer = $this->registeredCustomer(phone: '+8801712345678', password: 'someone-elses-password');
        $customer = $this->registeredCustomer(phone: '01712345678');

        $this->postJson(route('auth.login'), ['login' => '01712345678', 'password' => self::PASSWORD])->assertOk();

        $this->assertSame(1, $customer->tokens()->count());
        $this->assertSame(0, $otherCustomer->tokens()->count());
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->registeredCustomer(phone: '+8801712345678');

        $this->postJson(route('auth.login'), ['login' => '01712345678', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_a_guest_checkout_contact_without_a_password_cannot_log_in(): void
    {
        Contact::query()->create(['name' => 'Guest', 'phone' => '+8801712345678', 'password' => null]);

        $this->postJson(route('auth.login'), ['login' => '01712345678', 'password' => ''])
            ->assertUnprocessable();
        $this->postJson(route('auth.login'), ['login' => '01712345678', 'password' => 'anything'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLogins(): array
    {
        return [
            'malformed email' => ['rahim@'],
            'too short for a mobile number' => ['0171234'],
            'landline number' => ['029123456'],
        ];
    }

    #[DataProvider('invalidLogins')]
    public function test_a_login_that_is_neither_an_email_nor_a_mobile_number_is_refused(string $login): void
    {
        $this->postJson(route('auth.login'), ['login' => $login, 'password' => self::PASSWORD])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');
    }

    private function registeredCustomer(
        ?string $email = null,
        ?string $phone = null,
        string $password = self::PASSWORD,
    ): Contact {
        return Contact::query()->create([
            'name' => 'Rahim',
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($password),
        ]);
    }
}
