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
            $table->string('tenant_id')->index();
            $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('engine_started_at')->nullable();
            $table->timestamp('engine_stopped_at')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->integer('minutes_driven')->nullable();
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
