<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('gift_product_variant_id')
                ->nullable()
                ->after('pre_owned_id')
                ->constrained('gift_product_variants')
                ->onDelete('set null');

            $table->foreignId('product_care_id')
                ->nullable()
                ->after('gift_product_variant_id')
                ->constrained('product_cares')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['gift_product_variant_id']);
            $table->dropForeign(['product_care_id']);
        });
    }
};
