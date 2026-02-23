<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tables = [
            'users',
            'roles',
            'permissions',
            'vehicles',
            'trips',
            'reservations',
            'tickets',
            'ticket_messages',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'roles',
            'permissions',
            'vehicles',
            'trips',
            'reservations',
            'tickets',
            'ticket_messages',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('deleted_at');
                });
            }
        }
    }
};
