<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            
            // Relación con la reserva
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade')->unique();

            // Tiempos
            $table->timestamp('engine_started_at');
            $table->timestamp('engine_stopped_at')->nullable();

            // Dinero
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('penalty_amount', 10, 2)->default(0);

            // Datos del viaje
            $table->integer('minutes_driven')->nullable();
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            
            // 🔥 FALTABA ESTA LÍNEA (Visible en tu foto 2)
            $table->text('notes')->nullable(); 

            $table->timestamps();   // created_at, updated_at
            $table->softDeletes();  // deleted_at (Visible en tu foto 2)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};