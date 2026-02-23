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
            
            // RELATIONS
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');

            // DATES
            // scheduled_start: When they want the car.
            $table->timestamp('scheduled_start'); 
            
            // activation_deadline: Limit time to pick up the car (Start + 20min).
            $table->timestamp('activation_deadline')->nullable();
            
            // CANCELLATIONS
            $table->timestamp('cancelled_at')->nullable(); // Cancellation date
            
            // --- NEW COLUMN ---
            // Here we store the fee if cancelled with less than 24h notice.
            // Nullable because if the trip is completed normally, this will be empty.
            $table->decimal('cancellation_fee', 8, 2)->nullable();

            // STATUS
            $table->enum('status', ['pending', 'active', 'expired', 'completed', 'cancelled'])->default('pending');

            $table->timestamps(); // created_at and updated_at
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};