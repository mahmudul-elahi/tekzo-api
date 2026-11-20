<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_elite_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('elite_id')->constrained('elites')->cascadeOnDelete();
            $table->timestamp('achieved_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_elite_histories');
    }
};
