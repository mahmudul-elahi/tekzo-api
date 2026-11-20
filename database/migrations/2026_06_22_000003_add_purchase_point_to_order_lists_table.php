<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_lists', function (Blueprint $table) {
            $table->unsignedInteger('purchase_point')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_lists', function (Blueprint $table) {
            $table->dropColumn('purchase_point');
        });
    }
};
