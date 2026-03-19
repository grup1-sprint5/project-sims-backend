<?php

namespace Tests\Feature\MultiTenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIsolationTest extends TestCase
{
    use RefreshDatabase, TenantTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantData();
    }

    // ──────────────────────────────────────
    // GET /api/users (index)
    // ──────────────────────────────────────

    public function test_superadmin_sees_all_users(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/users');

        $response->assertOk();
        // 5 users: superAdmin, tenantAdmin1, tenantAdmin2, client1, client2
        $this->assertCount(5, $response->json());
    }

    public function test_tenant_admin_only_sees_own_tenant_users(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/users');

        $response->assertOk();
        $users = $response->json();
        // tenantAdmin1 + client1 = 2
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertEquals($this->tenant1->id, $user['tenant_id']);
        }
    }

    public function test_tenant_admin_does_not_see_other_tenant_users(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/users');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($this->tenantAdmin2->id));
        $this->assertFalse($ids->contains($this->client2->id));
    }

    // ──────────────────────────────────────
    // GET /api/users/{id} (show)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_view_own_tenant_user(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/users/{$this->client1->id}");

        $response->assertOk();
        $this->assertEquals($this->client1->id, $response->json('id'));
    }

    public function test_tenant_admin_cannot_view_other_tenant_user(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/users/{$this->client2->id}");

        // Global scope hides the user → 404
        $response->assertNotFound();
    }

    public function test_superadmin_can_view_any_user(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/users/{$this->client2->id}");

        $response->assertOk();
    }

    // ──────────────────────────────────────
    // POST /api/users (store)
    // ──────────────────────────────────────

    public function test_tenant_admin_creates_user_with_own_tenant_id(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->postJson('/api/users', [
                'name' => 'New User SIMS',
                'username' => 'newuser_sims',
                'email' => 'newuser@simscorp.com',
                'password' => 'password123',
                'active' => true,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@simscorp.com',
            'tenant_id' => $this->tenant1->id,
        ]);
    }

    public function test_client_cannot_create_user(): void
    {
        $response = $this->actingAs($this->client1)
            ->postJson('/api/users', [
                'name' => 'Hacker',
                'username' => 'hacker',
                'email' => 'hack@test.com',
                'password' => 'password123',
            ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────
    // PUT /api/users/{id} (update)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_update_own_tenant_user(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/users/{$this->client1->id}", [
                'name' => 'Updated Client',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $this->client1->id,
            'name' => 'Updated Client',
        ]);
    }

    public function test_tenant_admin_cannot_update_other_tenant_user(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/users/{$this->client2->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertNotFound();
    }

    // ──────────────────────────────────────
    // DELETE /api/users/{id} (destroy)
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_delete_other_tenant_user(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->deleteJson("/api/users/{$this->client2->id}");

        $response->assertNotFound();
    }

    public function test_superadmin_can_delete_any_user(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/users/{$this->client2->id}");

        $response->assertOk();
    }

    // ──────────────────────────────────────
    // Policy: user cannot see SuperAdmin details
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_see_superadmin_in_user_list(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/users');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        // SuperAdmin has no tenant → filtered out by global scope
        $this->assertFalse($ids->contains($this->superAdmin->id));
    }
}
