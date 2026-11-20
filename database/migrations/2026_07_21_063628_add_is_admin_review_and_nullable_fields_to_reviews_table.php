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
        Schema::table('reviews', function (Blueprint $table) {
            // Admin-authored reviews aren't tied to a real customer purchase,
            // so both FKs need to become optional — the foreign key
            // constraints themselves are untouched (NULL is simply never
            // checked against them).
            $table->unsignedBigInteger('contact_id')->nullable()->change();
            $table->unsignedBigInteger('order_detail_id')->nullable()->change();

            $table->boolean('is_admin_review')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('is_admin_review');
            $table->unsignedBigInteger('contact_id')->nullable(false)->change();
            $table->unsignedBigInteger('order_detail_id')->nullable(false)->change();
        });
    }
};
