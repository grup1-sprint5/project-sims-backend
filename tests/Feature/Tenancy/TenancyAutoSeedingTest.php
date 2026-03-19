<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyAutoSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_creation_auto_seeds_admin_user(): void
    {
        $tenantId = 'test-autoseed-' . now()->timestamp;

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Test Autoseed Tenant',
            'email' => 'info@testautoseed.example.com',
            'active' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            // Verify admin user was created
            $adminUser = \App\Models\User::where('username', 'admin-' . $tenantId)->first();
            $this->assertNotNull($adminUser, 'Admin user should be created automatically');

            // Verify admin has correct properties
            // Email is derived from tenant domain: admin@{domain}
            $this->assertEquals('admin@testautoseed.example.com', $adminUser->email);
            $this->assertTrue($adminUser->active);
            $this->assertEquals($tenantId, $adminUser->tenant_id);

            // Verify admin has TenantAdmin role
            $this->assertTrue($adminUser->hasRole('TenantAdmin'));

            // Verify roles exist in tenant schema
            $roles = \Spatie\Permission\Models\Role::pluck('name')->toArray();
            $this->assertContains('SuperAdmin', $roles);
            $this->assertContains('TenantAdmin', $roles);
            $this->assertContains('Client', $roles);
            $this->assertContains('Maintenance', $roles);

            // Verify permissions exist in tenant schema
            $permissionsCount = \Spatie\Permission\Models\Permission::count();
            $this->assertGreaterThan(0, $permissionsCount, 'Permissions should be seeded');
        } finally {
            tenancy()->end();
        }
    }

    public function test_admin_user_can_login(): void
    {
        $tenantId = 'test-login-' . now()->timestamp;

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Test Login Tenant',
            'email' => 'admin@testlogin.example.com',
            'active' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            $adminUser = \App\Models\User::where('username', 'admin-' . $tenantId)->first();
            $defaultPassword = 'change-me-' . $tenantId;

            // Verify password
            $this->assertTrue(
                \Illuminate\Support\Facades\Hash::check($defaultPassword, $adminUser->password),
                'Admin user should have the default password'
            );
        } finally {
            tenancy()->end();
        }
    }

    public function test_tenant_migrations_run_during_creation(): void
    {
        $tenantId = 'test-migrations-' . now()->timestamp;

        $tenant = Tenant::create([
            'id' => $tenantId,
            'name' => 'Test Migrations Tenant',
            'email' => 'info@testmigrations.example.com',
            'active' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            // Verify migrations table exists (indicates migrations ran)
            $migrationTableExists = \Illuminate\Support\Facades\Schema::hasTable('migrations');
            $this->assertTrue($migrationTableExists, 'Migrations table should exist after tenant creation');

            // Verify users table exists
            $usersTableExists = \Illuminate\Support\Facades\Schema::hasTable('users');
            $this->assertTrue($usersTableExists, 'Users table should exist after migrations');

            // Verify that at least one user exists (the auto-seeded admin)
            $userCount = \App\Models\User::count();
            $this->assertGreaterThanOrEqual(1, $userCount, 'At least the admin user should exist');
        } finally {
            tenancy()->end();
        }
    }
}
