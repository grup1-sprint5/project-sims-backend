<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckTenantActiveSimpleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_tenant_gets_403_response(): void
    {
        $tenant = Tenant::create([
            'id' => 'inactive-simple-test',
            'name' => 'Inactive Tenant',
            'email' => 'info@inactive-simple-test.example.com',
            'active' => false,  // Explicitly inactive
        ]);

        // Try to login with inactive tenant - should get 403
        $response = $this->withHeader('X-Tenant', 'inactive-simple-test')
            ->postJson('/api/login', [
                'email' => 'admin@inactive-simple-test.example.com',
                'password' => 'any-password',
            ]);

        // Should get 403 Forbidden due to CheckTenantActive middleware
        // (middleware rejects before auth check)
        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'tenant_inactive',
        ]);
    }

    public function test_central_health_endpoint_works(): void
    {
        // Central routes don't have the CheckTenantActive middleware
        $response = $this->getJson('/api/health');

        // Should succeed regardless
        $response->assertStatus(200);
        $this->assertEquals('ok', $response->json('status'));
    }
}
