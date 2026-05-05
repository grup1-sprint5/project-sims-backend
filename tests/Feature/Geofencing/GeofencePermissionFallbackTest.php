<?php

namespace Tests\Feature\Geofencing;

use App\Http\Middleware\AuthenticateTenantToken;
use App\Http\Middleware\CheckTenantActive;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Models\Geofence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeofencePermissionFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => database_path('migrations/tenant'),
            '--realpath' => true,
        ])->run();

        $this->withoutMiddleware([
            InitializeTenancyByDomainOrHeader::class,
            CheckTenantActive::class,
            AuthenticateTenantToken::class,
        ]);

        $tenantId = 'geo-sims-' . Str::lower(Str::random(8));

        $tenant = Tenant::withoutEvents(fn () => Tenant::create([
            'id' => $tenantId,
            'name' => 'SIMS Corp',
            'slug' => $tenantId,
            'tax_id' => 'B12345678',
            'email' => 'info@simscorp.com',
            'active' => true,
        ]));

        $user = User::withoutGlobalScopes()->create([
            'email' => 'geofence-viewer+' . Str::lower(Str::random(6)) . '@test.com',
            'name' => 'Geofence Viewer',
            'username' => 'geofence_viewer_' . Str::lower(Str::random(6)),
            'password' => 'password',
            'active' => true,
            'tenant_id' => $tenant->id,
        ]);

        $vehiclesView = Permission::firstOrCreate([
            'name' => 'vehicles.view',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'vehicles.manage',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'Client',
            'guard_name' => 'web',
        ])->syncPermissions([$vehiclesView]);

        $user->syncRoles(['Client']);

        Geofence::withoutGlobalScopes()->create([
            'name' => 'Zona Prova',
            'tenant_id' => $tenant->id,
            'type' => 'circle',
            'center_lat' => 41.3851,
            'center_lng' => 2.1734,
            'radius_m' => 100,
            'rule_type' => 'allow',
            'active' => true,
        ]);

        $this->actingAs($user);
    }

    public function test_geofences_index_ignores_missing_geofences_view_permission(): void
    {
        Permission::query()
            ->where('name', 'geofences.view')
            ->delete();

        $this->getJson('/api/geofences?page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_geofence_events_index_ignores_missing_geofences_view_permission(): void
    {
        Permission::query()
            ->where('name', 'geofences.view')
            ->delete();

        $this->getJson('/api/geofence-events?page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }
}
