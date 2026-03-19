<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckTenantActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_tenant_gets_403_on_login(): void
    {
        $tenant = Tenant::create([
            'id' => 'inactive-test',
            'name' => 'Inactive Tenant',
            'email' => 'info@inactive-test.example.com',
            'active' => false,  // Explicitly inactive
        ]);

        // Initialize to create schema
        tenancy()->initialize($tenant);
        tenancy()->end();

        // Try to login with inactive tenant - should get 403
        $response = $this->withHeader('X-Tenant', 'inactive-test')
            ->postJson('/api/login', [
                'email' => 'admin@inactive-test.example.com',
                'password' => 'change-me-inactive-test',
            ]);

        // Should get 403 Forbidden due to CheckTenantActive middleware
        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'tenant_inactive',
        ]);
    }

    public function test_active_tenant_can_login(): void
    {
        $tenant = Tenant::create([
            'id' => 'active-test',
            'name' => 'Active Tenant',
            'email' => 'info@active-test.example.com',
            'active' => true,  // Active
        ]);

        // Initialize to create schema and admin user
        tenancy()->initialize($tenant);
        tenancy()->end();

        $adminPassword = 'change-me-' . $tenant->id;

        // Try to login with active tenant
        $response = $this->withHeader('X-Tenant', 'active-test')
            ->postJson('/api/login', [
                'email' => 'admin@active-test.example.com',
                'password' => $adminPassword,
            ]);

        // Should succeed with 200
        $response->assertStatus(200);
        $this->assertNotNull($response->json('token'));
    }

    public function test_deactivated_tenant_rejects_further_requests(): void
    {
        $tenant = Tenant::create([
            'id' => 'deactivated-test',
            'name' => 'Deactivated Tenant',
            'email' => 'info@deactivated-test.example.com',
            'active' => true,
        ]);

        tenancy()->initialize($tenant);
        tenancy()->end();

        $adminPassword = 'change-me-' . $tenant->id;

        // Get auth token while active
        $loginResponse = $this->withHeader('X-Tenant', 'deactivated-test')
            ->postJson('/api/login', [
                'email' => 'admin@deactivated-test.example.com',
                'password' => $adminPassword,
            ]);

        $this->assertNotNull($loginResponse->json('token'));
        $token = $loginResponse->json('token');

        // Now deactivate the tenant
        $tenant->update(['active' => false]);

        // Try to use the token on deactivated tenant - should get 403
        $response = $this->withHeader('X-Tenant', 'deactivated-test')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        // Should get 403 because tenant is inactive (middleware runs first)
        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'tenant_inactive',
        ]);
    }
}
