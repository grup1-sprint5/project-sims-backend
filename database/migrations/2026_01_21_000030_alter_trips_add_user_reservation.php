<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('vehicle_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('trips', 'reservation_id')) {
                $table->unsignedBigInteger('reservation_id')->nullable()->after('user_id');
                $table->foreign('reservation_id')->references('id')->on('reservations')->onDelete('set null');
            }

            // drop old driver_id if present
            if (Schema::hasColumn('trips', 'driver_id')) {
                $table->dropForeign(['driver_id']);
                $table->dropColumn('driver_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'reservation_id')) {
                $table->dropForeign(['reservation_id']);
                $table->dropColumn('reservation_id');
            }
            if (Schema::hasColumn('trips', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (!Schema::hasColumn('trips', 'driver_id')) {
                $table->unsignedBigInteger('driver_id')->nullable()->after('vehicle_id');
                $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            }
        });
    }
};
