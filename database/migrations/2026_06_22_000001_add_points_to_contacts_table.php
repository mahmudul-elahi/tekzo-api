<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('total_points')->default(0)->after('date_of_birth');
            $table->foreignId('elite_id')->nullable()->constrained('elites')->nullOnDelete()->after('total_points');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['elite_id']);
            $table->dropColumn(['total_points', 'elite_id']);
        });
    }
};
