<?php

namespace Tests\Feature\MultiTenancy;

use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleIsolationTest extends TestCase
{
    use RefreshDatabase, TenantTestHelpers;

    protected Role $customRole1;
    protected Role $customRole2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantData();

        // Create custom roles per tenant
        $this->customRole1 = Role::create([
            'name' => 'CustomRole_SIMS',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant1->id,
        ]);

        $this->customRole2 = Role::create([
            'name' => 'CustomRole_EcoMove',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant2->id,
        ]);
    }

    // ──────────────────────────────────────
    // GET /api/roles (index)
    // ──────────────────────────────────────

    public function test_superadmin_sees_all_roles(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/roles');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('SuperAdmin'));
        $this->assertTrue($names->contains('CustomRole_SIMS'));
        $this->assertTrue($names->contains('CustomRole_EcoMove'));
    }

    public function test_tenant_admin_sees_system_roles_and_own_custom_roles(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/roles');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        // Sees system roles (except SuperAdmin)
        $this->assertTrue($names->contains('TenantAdmin'));
        $this->assertTrue($names->contains('Client'));
        $this->assertTrue($names->contains('TenantWorker'));

        // Sees own custom role
        $this->assertTrue($names->contains('CustomRole_SIMS'));

        // Does NOT see SuperAdmin or other tenant's custom role
        $this->assertFalse($names->contains('SuperAdmin'));
        $this->assertFalse($names->contains('CustomRole_EcoMove'));
    }

    public function test_tenant_admin_does_not_see_other_tenant_custom_roles(): void
    {
        $response = $this->actingAs($this->tenantAdmin2)
            ->getJson('/api/roles');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('CustomRole_EcoMove'));
        $this->assertFalse($names->contains('CustomRole_SIMS'));
    }

    // ──────────────────────────────────────
    // GET /api/roles/{id} (show)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_view_own_custom_role(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/roles/{$this->customRole1->id}");

        $response->assertOk();
    }

    public function test_tenant_admin_cannot_view_other_tenant_custom_role(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/roles/{$this->customRole2->id}");

        $response->assertForbidden();
    }

    public function test_tenant_admin_cannot_view_superadmin_role(): void
    {
        $superAdminRole = Role::findByName('SuperAdmin');

        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/roles/{$superAdminRole->id}");

        $response->assertForbidden();
    }

    // ──────────────────────────────────────
    // POST /api/roles (store)
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_create_role_without_permission(): void
    {
        // TenantAdmin has roles.view but NOT roles.manage
        $response = $this->actingAs($this->tenantAdmin1)
            ->postJson('/api/roles', [
                'name' => 'NewRole',
            ]);

        $response->assertForbidden();
    }

    public function test_superadmin_creates_role_without_tenant_id(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/roles', [
                'name' => 'GlobalRole',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('roles', [
            'name' => 'GlobalRole',
            'tenant_id' => null,
        ]);
    }

    // ──────────────────────────────────────
    // PUT /api/roles/{id} (update)
    // ──────────────────────────────────────

    public function test_superadmin_cannot_modify_protected_roles(): void
    {
        $clientRole = Role::findByName('Client');

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/roles/{$clientRole->id}", [
                'name' => 'RenamedClient',
            ]);

        $response->assertForbidden();
    }

    public function test_tenant_admin_cannot_update_other_tenant_custom_role(): void
    {
        // Give tenantAdmin1 roles.manage permission for this test
        $this->tenantAdmin1->givePermissionTo('roles.manage');

        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/roles/{$this->customRole2->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────
    // DELETE /api/roles/{id} (destroy)
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_delete_other_tenant_custom_role(): void
    {
        // Give tenantAdmin1 roles.delete permission for this test
        $this->tenantAdmin1->givePermissionTo('roles.delete');

        $response = $this->actingAs($this->tenantAdmin1)
            ->deleteJson("/api/roles/{$this->customRole2->id}");

        $response->assertForbidden();
    }

    public function test_superadmin_cannot_delete_protected_roles(): void
    {
        $tenantAdminRole = Role::findByName('TenantAdmin');

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/roles/{$tenantAdminRole->id}");

        $response->assertForbidden();
    }

    public function test_superadmin_can_delete_custom_role(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/roles/{$this->customRole1->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $this->customRole1->id]);
    }
}
