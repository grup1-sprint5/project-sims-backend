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
            //
                $table->timestamp('scheduled_end')->nullable()->after('scheduled_start');
                $table->decimal('total_price', 10, 2)->nullable()->after('cancellation_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            //
                $table->dropColumn(['scheduled_end', 'total_price']);
        });
    }
};
