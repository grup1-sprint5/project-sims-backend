<?php

namespace Tests\Feature\Geofencing;

use App\Http\Middleware\CheckTenantActive;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_geofences_requires_tenant_header_and_returns_400_without_it(): void
    {
        $response = $this->getJson('/api/geofences?page=1');

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'tenant_not_identified',
        ]);
    }

    public function test_geofences_requires_auth_and_returns_401_without_token(): void
    {
        $this->withoutMiddleware([
            InitializeTenancyByDomainOrHeader::class,
            CheckTenantActive::class,
        ]);

        $response = $this->withHeader('X-Tenant', 'sims-corp')
            ->getJson('/api/geofences?page=1');

        $response->assertStatus(401);
    }
}
