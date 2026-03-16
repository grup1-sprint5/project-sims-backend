<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->timestamp('scheduled_start');
            $table->timestamp('activation_deadline')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('cancellation_fee', 8, 2)->nullable();
            $table->enum('status', ['pending', 'active', 'expired', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
