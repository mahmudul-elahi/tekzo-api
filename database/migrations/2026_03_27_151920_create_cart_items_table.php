<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(App\Models\Cart\Cart::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(App\Models\Product\Product::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Product\ProductVariant::class)->constrained()->cascadeOnDelete();
            $table->boolean('is_select')->default(true);
            $table->decimal('booking_amount')->default(0);
            $table->json('attributes')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->enum('discount_type', ['fixed', 'percent'])->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
