<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerProfileUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('address')->nullable();
            $table->string('password')->nullable();
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
    }

    public function test_a_customer_can_add_a_secondary_phone_number_and_an_address(): void
    {
        $customer = $this->signedInCustomer();

        $this->postJson(route('customer.profile.update'), [
            'name' => 'Rahim Uddin',
            'secondary_phone' => '01812-345678',
            'address' => 'House 12, Road 5, Dhanmondi',
        ])->assertOk();

        $this->assertDatabaseHas('contacts', [
            'id' => $customer->id,
            'name' => 'Rahim Uddin',
            'secondary_phone' => '+8801812345678',
            'address' => 'House 12, Road 5, Dhanmondi',
        ]);
        $this->getJson(route('customer.profile.index'))
            ->assertOk()
            ->assertJsonPath('data.secondary_phone', '+8801812345678')
            ->assertJsonPath('data.address', 'House 12, Road 5, Dhanmondi');
    }

    public function test_a_secondary_phone_number_can_be_removed(): void
    {
        $customer = $this->signedInCustomer(secondaryPhone: '+8801812345678');

        $this->postJson(route('customer.profile.update'), ['name' => 'Rahim', 'secondary_phone' => null])
            ->assertOk();

        $this->assertNull($customer->fresh()->secondary_phone);
    }

    public function test_an_invalid_secondary_phone_number_is_refused(): void
    {
        $this->signedInCustomer();

        $this->postJson(route('customer.profile.update'), ['name' => 'Rahim', 'secondary_phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('secondary_phone');
    }

    private function signedInCustomer(?string $secondaryPhone = null): Contact
    {
        $customer = Contact::query()->create([
            'name' => 'Rahim',
            'email' => 'rahim@example.com',
            'phone' => '+8801712345678',
            'secondary_phone' => $secondaryPhone,
            'password' => 'secret-password',
        ]);

        Sanctum::actingAs($customer, ['*'], 'sanctum');
        Sanctum::actingAs($customer, ['*'], 'customer');

        return $customer;
    }
}
