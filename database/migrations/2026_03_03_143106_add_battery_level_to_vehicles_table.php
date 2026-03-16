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
        if (!Schema::hasTable('vehicles') || Schema::hasColumn('vehicles', 'battery_level')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->integer('battery_level')->default(100)->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('vehicles') || !Schema::hasColumn('vehicles', 'battery_level')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('battery_level');
        });
    }
};
