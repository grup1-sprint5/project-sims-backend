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
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('scheduled_end')->nullable()->after('scheduled_start');
            $table->decimal('total_price', 8, 2)->nullable()->after('cancellation_fee');
            $table->foreignId('tenant_id')->nullable()->after('vehicle_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['scheduled_end', 'total_price', 'tenant_id']);
        });
    }
};
