<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('license_plate')->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->boolean('active')->default(true);
            $table->decimal('price_per_minute', 8, 2)->nullable();
            $table->string('image_url')->nullable();
            $table->integer('battery_level')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
