<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
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
            $table->unsignedBigInteger('total_points')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->unsignedBigInteger('elite_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contact_elite_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('elite_id');
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('new_district_id')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
        });
    }

    public function test_the_dashboard_returns_the_customers_overview(): void
    {
        $customer = Contact::query()->create([
            'name' => 'Rahim',
            'email' => 'rahim@example.com',
            'phone' => '+8801712345678',
            'password' => 'secret-password',
            'phone_verified_at' => now(),
        ]);
        $someoneElse = Contact::query()->create(['name' => 'Karim', 'password' => 'secret-password']);
        DB::table('carts')->insert([['contact_id' => $customer->id], ['contact_id' => $someoneElse->id]]);

        Sanctum::actingAs($customer, ['*'], 'sanctum');
        Sanctum::actingAs($customer, ['*'], 'customer');

        $this->getJson(route('customer.dashboard'))
            ->assertOk()
            ->assertJsonPath('data.profile.name', 'Rahim')
            ->assertJsonPath('data.profile.is_phone_verified', true)
            ->assertJsonPath('data.statistics.totalOrder', 0)
            ->assertJsonPath('data.statistics.totalCart', 1)
            ->assertJsonPath('data.recent_orders', [])
            ->assertJsonPath('data.default_addresses.shippingAddress', null)
            ->assertJsonPath('data.default_addresses.billingAddress', null);
    }

    public function test_a_guest_cannot_see_a_dashboard(): void
    {
        $this->getJson(route('customer.dashboard'))->assertUnauthorized();
    }
}
