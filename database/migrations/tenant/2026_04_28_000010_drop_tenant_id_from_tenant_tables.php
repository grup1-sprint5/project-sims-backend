<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropTenantIdColumn('users');
        $this->dropTenantIdColumn('vehicles');
        $this->dropTenantIdColumn('reservations');
        $this->dropTenantIdColumn('tickets');
        $this->dropTenantIdColumn('trips');

        if (Schema::hasColumn('ticket_messages', 'tenant_id')) {
            Schema::table('ticket_messages', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasColumn('roles', 'tenant_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasColumn('vehicle_locations', 'tenant_id')) {
            Schema::table('vehicle_locations', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'license_plate']);
                $table->dropColumn('tenant_id');
            });
        }

        // Ensure license_plate remains unique within each tenant schema.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS vehicle_locations_license_plate_unique ON vehicle_locations (license_plate)');
    }

    public function down(): void
    {
        $this->addTenantIdColumn('users', false);
        $this->addTenantIdColumn('vehicles', false);
        $this->addTenantIdColumn('reservations', false);
        $this->addTenantIdColumn('tickets', false);
        $this->addTenantIdColumn('trips', false);

        if (!Schema::hasColumn('ticket_messages', 'tenant_id')) {
            Schema::table('ticket_messages', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('roles', 'tenant_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
        }

        if (!Schema::hasColumn('vehicle_locations', 'tenant_id')) {
            Schema::table('vehicle_locations', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
        }

        DB::statement('DROP INDEX IF EXISTS vehicle_locations_license_plate_unique');

        if (Schema::hasColumn('vehicle_locations', 'tenant_id')) {
            Schema::table('vehicle_locations', function (Blueprint $table) {
                $table->unique(['tenant_id', 'license_plate']);
            });
        }
    }

    private function dropTenantIdColumn(string $table): void
    {
        if (!Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }

    private function addTenantIdColumn(string $table, bool $nullable): void
    {
        if (Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($nullable) {
            $column = $table->string('tenant_id');
            if ($nullable) {
                $column->nullable();
            }
            $column->index();
        });
    }
};
