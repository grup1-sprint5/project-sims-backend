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
        if (!Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'scheduled_end')) {
                $table->timestamp('scheduled_end')->nullable()->after('scheduled_start');
            }
            if (!Schema::hasColumn('reservations', 'total_price')) {
                $table->decimal('total_price', 8, 2)->nullable()->after('cancellation_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('reservations', 'scheduled_end')) {
                $drop[] = 'scheduled_end';
            }
            if (Schema::hasColumn('reservations', 'total_price')) {
                $drop[] = 'total_price';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
