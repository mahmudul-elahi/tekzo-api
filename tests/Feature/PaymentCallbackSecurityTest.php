<?php

namespace Tests\Feature;

use App\Enums\OnlinePaymentStatusEnum;
use App\Models\Order\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentCallbackSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        DB::table('tenants')->insert(['id' => 1, 'slug' => 'tekzo']);

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('payment_method')->default('online');
            $table->string('payment_status')->nullable();
            $table->string('paymentId')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->double('payable_amount', 8, 2)->default(0);
            $table->double('due_amount', 8, 2)->default(0);
            $table->double('grand_total', 8, 2)->default(0);
            $table->double('total_booking_amount', 8, 2)->default(0);
            $table->json('additional_value')->nullable();
            $table->timestamps();
        });
    }

    public function test_cancel_callback_does_not_overwrite_successful_order(): void
    {
        $order = Order::create([
            'transaction_id' => 'TRAN_SUCCESS_1',
            'invoice_number' => 'ORD-20261120-000001',
            'payment_status' => OnlinePaymentStatusEnum::Successful->value,
            'grand_total' => 1000,
            'payable_amount' => 1000,
            'due_amount' => 0,
        ]);

        $response = $this->post('/api/v1/customer/cancel', [
            'tran_id' => 'TRAN_SUCCESS_1',
        ]);

        $order->refresh();
        $this->assertEquals(OnlinePaymentStatusEnum::Successful->value, $order->payment_status);
        $this->assertEquals(1000, $order->payable_amount);
        $this->assertEquals(0, $order->due_amount);
        $response->assertRedirect(config('services.front_base_url').'/order/successful?invoice='.$order->invoice_number);
    }

    public function test_fail_callback_does_not_overwrite_successful_order(): void
    {
        $order = Order::create([
            'transaction_id' => 'TRAN_SUCCESS_2',
            'invoice_number' => 'ORD-20261120-000002',
            'payment_status' => OnlinePaymentStatusEnum::Successful->value,
            'grand_total' => 2000,
            'payable_amount' => 2000,
            'due_amount' => 0,
        ]);

        $response = $this->post('/api/v1/customer/fail', [
            'tran_id' => 'TRAN_SUCCESS_2',
        ]);

        $order->refresh();
        $this->assertEquals(OnlinePaymentStatusEnum::Successful->value, $order->payment_status);
        $this->assertEquals(2000, $order->payable_amount);
        $this->assertEquals(0, $order->due_amount);
        $response->assertRedirect(config('services.front_base_url').'/order/successful?invoice='.$order->invoice_number);
    }

    public function test_bkash_callback_does_not_overwrite_successful_order(): void
    {
        $order = Order::create([
            'paymentId' => 'BKASH_PAY_1',
            'invoice_number' => 'ORD-20261120-000003',
            'payment_status' => OnlinePaymentStatusEnum::Successful->value,
            'grand_total' => 3000,
            'payable_amount' => 3000,
            'due_amount' => 0,
        ]);

        $response = $this->get('/api/v1/customer/bkash-callback?paymentID=BKASH_PAY_1&status=failure');

        $order->refresh();
        $this->assertEquals(OnlinePaymentStatusEnum::Successful->value, $order->payment_status);
        $this->assertEquals(3000, $order->payable_amount);
        $this->assertEquals(0, $order->due_amount);
        $response->assertRedirect(config('services.front_base_url').'/order/successful?invoice_number='.$order->invoice_number);
    }
}
