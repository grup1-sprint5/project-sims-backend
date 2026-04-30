<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (!Schema::hasTable('geofences')) {
            Schema::create('geofences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('tenant_id')->index();
                $table->string('name');
                $table->enum('type', ['polygon', 'circle']);
                $table->json('geometry_geojson')->nullable();
                $table->decimal('center_lat', 10, 8)->nullable();
                $table->decimal('center_lng', 11, 8)->nullable();
                $table->unsignedInteger('radius_m')->nullable();
                $table->enum('rule_type', ['allow', 'forbid'])->default('allow');
                $table->boolean('active')->default(true)->index();
                $table->json('schedule')->nullable();
                $table->unsignedInteger('hysteresis_m')->default(15);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'name']);
                $table->index(['tenant_id', 'type']);
            });
        }

        if (!Schema::hasTable('geofence_assignments')) {
            Schema::create('geofence_assignments', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->uuid('geofence_id');
                $table->enum('assign_type', ['vehicle', 'fleet']);
                $table->string('assign_id');
                $table->timestamps();

                $table->foreign('geofence_id')->references('id')->on('geofences')->onDelete('cascade');
                $table->index(['tenant_id', 'assign_type', 'assign_id']);
                $table->unique(['tenant_id', 'geofence_id', 'assign_type', 'assign_id'], 'geo_assign_unique');
            });
        }

        if (!Schema::hasTable('geofence_events')) {
            Schema::create('geofence_events', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->uuid('geofence_id');
                $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
                $table->enum('event_type', ['enter', 'exit', 'violation']);
                $table->decimal('position_lat', 10, 8);
                $table->decimal('position_lng', 11, 8);
                $table->timestamp('occurred_at')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('geofence_id')->references('id')->on('geofences')->onDelete('cascade');
                $table->index(['tenant_id', 'vehicle_id', 'occurred_at'], 'geo_events_tenant_vehicle_occ_idx');
                $table->index(['tenant_id', 'geofence_id', 'occurred_at'], 'geo_events_tenant_geofence_occ_idx');
                $table->index(['tenant_id', 'event_type', 'occurred_at'], 'geo_events_tenant_type_occ_idx');
            });
        }

        if (!Schema::hasTable('geofence_vehicle_states')) {
            Schema::create('geofence_vehicle_states', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->uuid('geofence_id');
                $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
                $table->boolean('inside')->default(false);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->foreign('geofence_id')->references('id')->on('geofences')->onDelete('cascade');
                $table->unique(['tenant_id', 'geofence_id', 'vehicle_id'], 'geo_state_tenant_geofence_vehicle_unique');
            });
        }

        $this->ensurePostgisColumnsIfAvailable();
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_vehicle_states');
        Schema::dropIfExists('geofence_events');
        Schema::dropIfExists('geofence_assignments');
        Schema::dropIfExists('geofences');
    }

    private function ensurePostgisColumnsIfAvailable(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            $available = DB::selectOne("SELECT 1 AS ok FROM pg_available_extensions WHERE name = 'postgis' LIMIT 1");
            if (!$available) {
                return;
            }

            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        } catch (\Throwable $exception) {
            report($exception);
            return;
        }

        try {
            DB::statement('ALTER TABLE geofences ADD COLUMN IF NOT EXISTS geometry geometry(POLYGON, 4326)');
            DB::statement('ALTER TABLE geofence_events ADD COLUMN IF NOT EXISTS position geometry(POINT, 4326)');
            DB::statement('CREATE INDEX IF NOT EXISTS geofences_geometry_gix ON geofences USING GIST (geometry)');
            DB::statement('CREATE INDEX IF NOT EXISTS geofence_events_position_gix ON geofence_events USING GIST (position)');
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
};
