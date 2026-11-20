<?php

namespace Tests\Feature;

use App\Models\Address\CustomerAddress;
use App\Models\Contact\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressSecurityTest extends TestCase
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
            $table->unsignedBigInteger('total_points')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('elite_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('new_districts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('new_district_id')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('street')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
        });
    }

    public function test_customer_cannot_view_another_customers_address(): void
    {
        DB::table('new_districts')->insert(['id' => 1, 'name' => 'Dhaka']);
        DB::table('areas')->insert(['id' => 1, 'name' => 'Mirpur']);

        $customerA = Contact::create(['name' => 'Customer A', 'email' => 'a@example.com']);
        $customerB = Contact::create(['name' => 'Customer B', 'email' => 'b@example.com']);

        $addressA = CustomerAddress::create([
            'contact_id' => $customerA->id,
            'street' => 'Dhanmondi 32',
            'label' => 'home',
        ]);

        Sanctum::actingAs($customerB, ['*'], 'customer');

        $response = $this->getJson("/api/v1/customer/addresses/{$addressA->id}");

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Address not found',
        ]);
    }

    public function test_customer_cannot_update_another_customers_address(): void
    {
        DB::table('new_districts')->insert(['id' => 1, 'name' => 'Dhaka']);
        DB::table('areas')->insert(['id' => 1, 'name' => 'Mirpur']);

        $customerA = Contact::create(['name' => 'Customer A', 'email' => 'a@example.com']);
        $customerB = Contact::create(['name' => 'Customer B', 'email' => 'b@example.com']);

        $addressA = CustomerAddress::create([
            'contact_id' => $customerA->id,
            'street' => 'Dhanmondi 32',
            'label' => 'home',
        ]);

        Sanctum::actingAs($customerB, ['*'], 'customer');

        $response = $this->putJson("/api/v1/customer/addresses/{$addressA->id}", [
            'name' => 'Hacked',
            'phone' => '01700000002',
            'address' => 'Chittagong',
            'new_district_id' => 1,
            'area_id' => 1,
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Dhanmondi 32', $addressA->fresh()->street);
    }

    public function test_customer_cannot_delete_another_customers_address(): void
    {
        DB::table('new_districts')->insert(['id' => 1, 'name' => 'Dhaka']);
        DB::table('areas')->insert(['id' => 1, 'name' => 'Mirpur']);

        $customerA = Contact::create(['name' => 'Customer A', 'email' => 'a@example.com']);
        $customerB = Contact::create(['name' => 'Customer B', 'email' => 'b@example.com']);

        $addressA = CustomerAddress::create([
            'contact_id' => $customerA->id,
            'street' => 'Dhanmondi 32',
            'label' => 'home',
        ]);

        Sanctum::actingAs($customerB, ['*'], 'customer');

        $response = $this->deleteJson("/api/v1/customer/addresses/{$addressA->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('customer_addresses', ['id' => $addressA->id]);
    }

    public function test_customer_can_delete_own_address(): void
    {
        DB::table('new_districts')->insert(['id' => 1, 'name' => 'Dhaka']);
        DB::table('areas')->insert(['id' => 1, 'name' => 'Mirpur']);

        $customerA = Contact::create(['name' => 'Customer A', 'email' => 'a@example.com']);

        $addressA = CustomerAddress::create([
            'contact_id' => $customerA->id,
            'street' => 'Dhanmondi 32',
            'label' => 'home',
        ]);

        Sanctum::actingAs($customerA, ['*'], 'customer');

        $response = $this->deleteJson("/api/v1/customer/addresses/{$addressA->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Address deleted successfully',
        ]);
        $this->assertDatabaseMissing('customer_addresses', ['id' => $addressA->id]);
    }
}
