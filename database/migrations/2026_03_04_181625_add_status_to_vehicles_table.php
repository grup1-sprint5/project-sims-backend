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
        if (!Schema::hasTable('vehicles')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'status')) {
                $table->string('status')->default('available')->after('model');
            }
            if (!Schema::hasColumn('vehicles', 'type')) {
                $table->string('type')->default('electric')->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('vehicles')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('vehicles', 'status')) {
                $drop[] = 'status';
            }
            if (Schema::hasColumn('vehicles', 'type')) {
                $drop[] = 'type';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
