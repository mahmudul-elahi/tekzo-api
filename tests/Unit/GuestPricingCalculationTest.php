<?php

namespace Tests\Unit;

use App\Http\Controllers\BuyNow\BuyNowController;
use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use App\Models\Product\VariantPrice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class GuestPricingCalculationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->boolean('is_ecommerce')->default(true);
            $table->double('booking_amount')->default(0);
            $table->unsignedBigInteger('purchase_point')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->boolean('stock')->default(true);
            $table->boolean('is_prebook')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('variant_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id');
            $table->double('max_retail_price')->default(0);
            $table->double('discount_percent')->default(0);
            $table->double('discount_fixed')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
        });

        Schema::create('offer_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('discount_type')->nullable();
            $table->double('discount_amount')->default(0);
            $table->timestamps();
        });
    }

    public function test_normal_product_with_percent_discount_calculates_currency_discount(): void
    {
        $product = new Product;
        $product->product_name = 'Test Phone';
        $product->save();

        $variant = new ProductVariant;
        $variant->product_id = $product->id;
        $variant->stock = true;
        $variant->save();

        $price = new VariantPrice;
        $price->product_variant_id = $variant->id;
        $price->max_retail_price = 50000;
        $price->discount_percent = 10;
        $price->discount_fixed = 0;
        $price->save();

        $controller = new BuyNowController;
        $method = new ReflectionMethod(BuyNowController::class, 'processCartItems');
        $method->setAccessible(true);

        $carts = [
            [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ],
        ];

        [$cartItems, $subTotal, $discountTotal, $totalBookingAmount] = $method->invoke($controller, $carts, false);

        // Subtotal = 50,000 * 2 = 100,000
        $this->assertEquals(100000, $subTotal);
        // 10% of 100,000 = 10,000 (NOT 10 * 2 = 20)
        $this->assertEquals(10000, $discountTotal);
        $this->assertEquals(90000, $subTotal - $discountTotal);
    }

    public function test_normal_product_with_fixed_discount_calculates_fixed_discount(): void
    {
        $product = new Product;
        $product->product_name = 'Test Phone Fixed';
        $product->save();

        $variant = new ProductVariant;
        $variant->product_id = $product->id;
        $variant->stock = true;
        $variant->save();

        $price = new VariantPrice;
        $price->product_variant_id = $variant->id;
        $price->max_retail_price = 20000;
        $price->discount_percent = 0;
        $price->discount_fixed = 500;
        $price->save();

        $controller = new BuyNowController;
        $method = new ReflectionMethod(BuyNowController::class, 'processCartItems');
        $method->setAccessible(true);

        $carts = [
            [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ],
        ];

        [$cartItems, $subTotal, $discountTotal, $totalBookingAmount] = $method->invoke($controller, $carts, false);

        // Subtotal = 20,000 * 3 = 60,000
        $this->assertEquals(60000, $subTotal);
        // Fixed 500 * 3 = 1500
        $this->assertEquals(1500, $discountTotal);
        $this->assertEquals(58500, $subTotal - $discountTotal);
    }
}
