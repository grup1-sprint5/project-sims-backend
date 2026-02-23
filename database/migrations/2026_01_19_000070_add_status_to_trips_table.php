<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Add an enum status column according to the schema provided
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])
                  ->default('scheduled')
                  ->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
