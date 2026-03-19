<?php

namespace Tests\Feature\MultiTenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantPolicyIsolationTest extends TestCase
{
    use RefreshDatabase, TenantTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantData();
    }

    // ──────────────────────────────────────
    // GET /api/tenants (index)
    // ──────────────────────────────────────

    public function test_superadmin_sees_all_tenants(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/tenants');

        $response->assertOk();
    }

    public function test_tenant_admin_can_list_tenants(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/tenants');

        // TenantAdmin has tenants.view → allowed
        $response->assertOk();
    }

    public function test_client_cannot_list_tenants(): void
    {
        $response = $this->actingAs($this->client1)
            ->getJson('/api/tenants');

        $response->assertForbidden();
    }

    // ──────────────────────────────────────
    // GET /api/tenants/{id} (show)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_view_own_tenant(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/tenants/{$this->tenant1->id}");

        $response->assertOk();
    }

    public function test_tenant_admin_cannot_view_other_tenant(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/tenants/{$this->tenant2->id}");

        $response->assertForbidden();
    }

    public function test_superadmin_can_view_any_tenant(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/tenants/{$this->tenant2->id}");

        $response->assertOk();
    }

    // ──────────────────────────────────────
    // PUT /api/tenants/{id} (update)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_update_own_tenant(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/tenants/{$this->tenant1->id}", [
                'name' => 'SIMS Corp Updated',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant1->id,
            'name' => 'SIMS Corp Updated',
        ]);
    }

    public function test_tenant_admin_cannot_update_other_tenant(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/tenants/{$this->tenant2->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────
    // POST /api/tenants (store)
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_create_tenant(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->postJson('/api/tenants', [
                'name' => 'Evil Corp',
                'slug' => 'evil-corp',
            ]);

        // TenantAdmin lacks tenants.manage
        $response->assertForbidden();
    }

    public function test_superadmin_can_create_tenant(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/tenants', [
                'name' => 'New Tenant',
                'slug' => 'new-tenant',
                'tax_id' => 'B99999999',
                'email' => 'new@tenant.com',
            ]);

        $response->assertCreated();
    }

    // ──────────────────────────────────────
    // DELETE /api/tenants/{id} (destroy)
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_delete_any_tenant(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->deleteJson("/api/tenants/{$this->tenant1->id}");

        $response->assertForbidden();
    }

    public function test_superadmin_can_delete_tenant(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/tenants/{$this->tenant2->id}");

        $response->assertOk();
        $this->assertSoftDeleted('tenants', ['id' => $this->tenant2->id]);
    }

    // ──────────────────────────────────────
    // PATCH /api/tenants/{id}/toggle-active
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_toggle_other_tenant_active(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->patchJson("/api/tenants/{$this->tenant2->id}/toggle-active");

        $response->assertForbidden();
    }
}
