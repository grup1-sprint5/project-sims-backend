<?php

namespace Tests\Feature\Geofencing;

use App\Http\Middleware\AuthenticateTenantToken;
use App\Http\Middleware\CheckTenantActive;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GeofenceApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $tenantAdmin1;
    private User $tenantAdmin2;
    private Vehicle $vehicle1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            InitializeTenancyByDomainOrHeader::class,
            CheckTenantActive::class,
            AuthenticateTenantToken::class,
        ]);

        Gate::before(fn () => true);

        $this->tenant1 = Tenant::firstOrCreate([
            'id' => 'geo-sims-corp',
        ], [
            'name' => 'SIMS Corp',
            'slug' => 'geo-sims-corp',
            'tax_id' => 'B12345678',
            'email' => 'info@simscorp.com',
            'active' => true,
        ]);

        $this->tenant2 = Tenant::firstOrCreate([
            'id' => 'geo-ecomove',
        ], [
            'name' => 'EcoMove',
            'slug' => 'geo-ecomove',
            'tax_id' => 'B87654321',
            'email' => 'info@ecomove.es',
            'active' => true,
        ]);

        $this->tenantAdmin1 = User::withoutGlobalScopes()->firstOrCreate([
            'email' => 'geofence-admin-sims@test.com',
        ], [
            'name' => 'Admin SIMS',
            'username' => 'geo_admin_sims',
            'password' => 'password',
            'active' => true,
            'tenant_id' => $this->tenant1->id,
        ]);

        $this->tenantAdmin2 = User::withoutGlobalScopes()->firstOrCreate([
            'email' => 'geofence-admin-eco@test.com',
        ], [
            'name' => 'Admin ECO',
            'username' => 'geo_admin_eco',
            'password' => 'password',
            'active' => true,
            'tenant_id' => $this->tenant2->id,
        ]);

        $this->vehicle1 = Vehicle::withoutGlobalScopes()->firstOrCreate([
            'license_plate' => 'GEO-1234',
        ], [
            'tenant_id' => $this->tenant1->id,
            'brand' => 'Toyota',
            'model' => 'Yaris',
            'active' => true,
        ]);
    }

    public function test_tenant_admin_can_create_polygon_and_circle_geofences(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $polygonResponse = $this->postJson('/api/geofences', [
            'name' => 'Zona Port',
            'type' => 'polygon',
            'polygon' => [
                [2.1700, 41.3800],
                [2.1800, 41.3800],
                [2.1800, 41.3900],
                [2.1700, 41.3800],
            ],
            'rule_type' => 'allow',
            'active' => true,
        ]);

        $polygonResponse->assertCreated();
        $polygonId = $polygonResponse->json('data.id');
        $this->assertNotEmpty($polygonId);

        $circleResponse = $this->postJson('/api/geofences', [
            'name' => 'Zona Centre',
            'type' => 'circle',
            'center' => ['lat' => 41.3851, 'lng' => 2.1734],
            'radius_m' => 200,
            'rule_type' => 'forbid',
            'active' => true,
        ]);

        $circleResponse->assertCreated();
        $this->assertEquals('circle', $circleResponse->json('data.type'));

        $updateResponse = $this->patchJson('/api/geofences/' . $polygonId, [
            'name' => 'Zona Port Nord',
            'active' => false,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Zona Port Nord')
            ->assertJsonPath('data.active', false);
    }

    public function test_assignments_and_event_flow_work_and_no_duplicates_are_emitted(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $geofenceResponse = $this->postJson('/api/geofences', [
            'name' => 'No Parking',
            'type' => 'circle',
            'center' => ['lat' => 41.3851, 'lng' => 2.1734],
            'radius_m' => 120,
            'rule_type' => 'forbid',
            'active' => true,
            'hysteresis_m' => 5,
        ])->assertCreated();

        $geofenceId = $geofenceResponse->json('data.id');

        $this->postJson("/api/geofences/{$geofenceId}/assignments", [
            'assign_type' => 'vehicle',
            'assign_id' => (string) $this->vehicle1->id,
        ])->assertCreated();

        $this->postJson("/api/geofences/{$geofenceId}/assignments", [
            'assign_type' => 'fleet',
            'assign_id' => 'electric',
        ])->assertCreated();

        // Initial state (outside) -> no events
        $this->postJson('/api/vehicle-positions', [
            'vehicle_id' => $this->vehicle1->id,
            'lat' => 41.3880,
            'lng' => 2.1790,
            'timestamp' => now()->toISOString(),
        ])->assertStatus(202)
          ->assertJsonPath('data.events_count', 0);

        // Outside -> inside => enter + violation
        $this->postJson('/api/vehicle-positions', [
            'vehicle_id' => $this->vehicle1->id,
            'lat' => 41.3851,
            'lng' => 2.1734,
            'timestamp' => now()->addSeconds(5)->toISOString(),
        ])->assertStatus(202)
          ->assertJsonPath('data.events_count', 2);

        // Still inside => no duplicates
        $this->postJson('/api/vehicle-positions', [
            'vehicle_id' => $this->vehicle1->id,
            'lat' => 41.3852,
            'lng' => 2.1735,
            'timestamp' => now()->addSeconds(10)->toISOString(),
        ])->assertStatus(202)
          ->assertJsonPath('data.events_count', 0);

        $eventsResponse = $this->getJson('/api/geofence-events?vehicle_id=' . $this->vehicle1->id);
        $eventsResponse->assertOk();
        $types = collect($eventsResponse->json('data'))->pluck('event_type')->all();

        $this->assertContains('enter', $types);
        $this->assertContains('violation', $types);
    }

    public function test_tenant_cannot_read_other_tenant_geofence(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $created = $this->postJson('/api/geofences', [
            'name' => 'Private Tenant1',
            'type' => 'circle',
            'center' => ['lat' => 41.3851, 'lng' => 2.1734],
            'radius_m' => 100,
            'rule_type' => 'allow',
        ])->assertCreated();

        $geofenceId = $created->json('data.id');

        $this->actingAs($this->tenantAdmin2)
            ->getJson('/api/geofences/' . $geofenceId)
            ->assertNotFound();
    }
}
