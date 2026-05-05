<?php

namespace Tests\Unit\Geofencing;

use App\Models\Geofence;
use App\Models\GeofenceAssignment;
use App\Models\Vehicle;
use App\Services\Geofencing\GeofenceEvaluatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceEvaluatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This unit test exercises tenant tables but does not bootstrap tenancy.
        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/2026_01_19_000040_create_vehicles_table.php'),
            '--realpath' => true,
        ])->run();

        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant/2026_04_23_120000_create_geofencing_tables.php'),
            '--realpath' => true,
        ])->run();
    }

    public function test_it_creates_enter_and_exit_events_without_duplicates(): void
    {
        $tenantId = 'tenant-unit';

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'license_plate' => 'UNIT-100',
            'brand' => 'Renault',
            'model' => 'Zoe',
            'active' => true,
        ]);

        $geofence = Geofence::create([
            'tenant_id' => $tenantId,
            'name' => 'Test Circle',
            'type' => 'circle',
            'center_lat' => 41.3851,
            'center_lng' => 2.1734,
            'radius_m' => 120,
            'rule_type' => 'forbid',
            'active' => true,
            'hysteresis_m' => 10,
        ]);

        GeofenceAssignment::create([
            'tenant_id' => $tenantId,
            'geofence_id' => $geofence->id,
            'assign_type' => 'vehicle',
            'assign_id' => (string) $vehicle->id,
        ]);

        $service = app(GeofenceEvaluatorService::class);

        // First sample initializes state only
        $events0 = $service->processVehiclePosition($vehicle, 41.3875, 2.1760, CarbonImmutable::now());
        $this->assertCount(0, $events0);

        // Outside -> inside => enter + violation
        $events1 = $service->processVehiclePosition($vehicle, 41.3851, 2.1734, CarbonImmutable::now()->addSeconds(10));
        $this->assertCount(2, $events1);
        $this->assertEquals('enter', $events1[0]->event_type);
        $this->assertEquals('violation', $events1[1]->event_type);

        // Still inside => no duplicates
        $events2 = $service->processVehiclePosition($vehicle, 41.3852, 2.1735, CarbonImmutable::now()->addSeconds(20));
        $this->assertCount(0, $events2);

        // Inside -> outside => exit
        $events3 = $service->processVehiclePosition($vehicle, 41.3885, 2.1790, CarbonImmutable::now()->addSeconds(30));
        $this->assertCount(1, $events3);
        $this->assertEquals('exit', $events3[0]->event_type);
    }
}
