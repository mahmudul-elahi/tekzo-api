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
        Schema::create('sell_device_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('ad_title', 120);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('new_district_id')->nullable()->constrained('new_districts')->nullOnDelete();
            $table->string('model');
            $table->enum('condition', ['brand_new', 'like_new', 'good', 'fair', 'poor']);
            $table->string('ram')->nullable();
            $table->string('storage')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_exchange_possible')->default(false);
            $table->decimal('asking_price', 10, 2);
            $table->boolean('is_negotiable')->default(false);
            $table->string('video_url')->nullable();
            $table->text('description');
            $table->string('phone_number');
            $table->tinyInteger('status')->default(2); // StatusEnum::Pending
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sell_device_request_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sell_device_request_id')->constrained('sell_device_requests')->cascadeOnDelete();
            $table->string('image');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('sell_device_request_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sell_device_request_id')->constrained('sell_device_requests')->cascadeOnDelete();
            $table->string('feature_name');
            $table->string('feature_value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sell_device_request_features');
        Schema::dropIfExists('sell_device_request_images');
        Schema::dropIfExists('sell_device_requests');
    }
};
