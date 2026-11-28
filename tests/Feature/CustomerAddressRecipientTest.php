<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAddressRecipientTest extends TestCase
{
    private int $districtId;

    private int $areaId;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('new_districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('new_district_id')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('recipient_name', 150)->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('street')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
        });

        $this->districtId = DB::table('new_districts')->insertGetId(['name' => 'Dhaka']);
        $this->areaId = DB::table('areas')->insertGetId(['name' => 'Dhanmondi']);

        $customer = Contact::query()->create(['name' => 'Rahim', 'email' => 'rahim@example.com', 'password' => 'secret-password']);
        Sanctum::actingAs($customer, ['*'], 'sanctum');
        Sanctum::actingAs($customer, ['*'], 'customer');
    }

    public function test_an_address_can_deliver_to_someone_else(): void
    {
        $this->postJson('/api/v1/customer/addresses', $this->address([
            'recipient_name' => 'Karim Uddin',
            'recipient_phone' => '01812-345678',
        ]))->assertOk();

        $this->assertDatabaseHas('customer_addresses', [
            'recipient_name' => 'Karim Uddin',
            'recipient_phone' => '+8801812345678',
        ]);
    }

    public function test_an_address_for_the_customer_themselves_needs_no_recipient(): void
    {
        $this->postJson('/api/v1/customer/addresses', $this->address())->assertOk();

        $this->assertDatabaseHas('customer_addresses', ['recipient_name' => null, 'recipient_phone' => null]);
    }

    public function test_a_recipient_needs_both_a_name_and_a_phone_number(): void
    {
        $this->postJson('/api/v1/customer/addresses', $this->address(['recipient_name' => 'Karim Uddin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_phone');

        $this->postJson('/api/v1/customer/addresses', $this->address(['recipient_phone' => '01812345678']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_name');
    }

    public function test_an_invalid_recipient_phone_number_is_refused(): void
    {
        $this->postJson('/api/v1/customer/addresses', $this->address([
            'recipient_name' => 'Karim Uddin',
            'recipient_phone' => '12345',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_phone');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function address(array $overrides = []): array
    {
        return [
            'new_district_id' => $this->districtId,
            'area_id' => $this->areaId,
            'street' => 'House 12, Road 5',
            'label' => 'home',
            ...$overrides,
        ];
    }
}
