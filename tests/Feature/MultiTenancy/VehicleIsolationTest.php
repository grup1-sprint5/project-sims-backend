<?php

namespace Tests\Feature\MultiTenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleIsolationTest extends TestCase
{
    use RefreshDatabase, TenantTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantData();
    }

    // ──────────────────────────────────────
    // GET /api/vehicles (index)
    // ──────────────────────────────────────

    public function test_superadmin_can_list_all_vehicles(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/vehicles');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_tenant_admin_only_lists_own_vehicles(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/vehicles');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->vehicle1->id, $data[0]['id']);
    }

    public function test_tenant_admin_does_not_see_other_tenant_vehicles_in_list(): void
    {
        $response = $this->actingAs($this->tenantAdmin2)
            ->getJson('/api/vehicles');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->vehicle2->id, $data[0]['id']);
        $ids = collect($data)->pluck('id');
        $this->assertFalse($ids->contains($this->vehicle1->id));
    }

    // ──────────────────────────────────────
    // GET /api/vehicles/{id} (show)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_view_own_tenant_vehicle(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/vehicles/{$this->vehicle1->id}");

        $response->assertOk();
    }

    public function test_tenant_admin_cannot_view_other_tenant_vehicle(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/vehicles/{$this->vehicle2->id}");

        // Global scope hides it → 404 (model not found)
        $response->assertNotFound();
    }

    public function test_superadmin_can_view_any_vehicle(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/vehicles/{$this->vehicle2->id}");

        $response->assertOk();
    }

    // ──────────────────────────────────────
    // POST /api/vehicles (store)
    // ──────────────────────────────────────

    public function test_tenant_admin_creates_vehicle_with_own_tenant_id(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->postJson('/api/vehicles', [
                'license_plate' => '0000-NEW',
                'brand' => 'Seat',
                'model' => 'Ibiza',
                'active' => true,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vehicles', [
            'license_plate' => '0000-NEW',
            'tenant_id' => $this->tenant1->id,
        ]);
    }

    public function test_client_cannot_create_vehicle(): void
    {
        $response = $this->actingAs($this->client1)
            ->postJson('/api/vehicles', [
                'license_plate' => '1111-NO',
                'brand' => 'Test',
                'model' => 'Test',
                'active' => true,
            ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────
    // PUT /api/vehicles/{id} (update)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_update_own_tenant_vehicle(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/vehicles/{$this->vehicle1->id}", [
                'brand' => 'Toyota Updated',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('vehicles', [
            'id' => $this->vehicle1->id,
            'brand' => 'Toyota Updated',
        ]);
    }

    public function test_tenant_admin_cannot_update_other_tenant_vehicle(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/vehicles/{$this->vehicle2->id}", [
                'brand' => 'Hacked',
            ]);

        // Global scope hides it → 404
        $response->assertNotFound();
    }

    // ──────────────────────────────────────
    // DELETE /api/vehicles/{id} (destroy)
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_delete_other_tenant_vehicle(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->deleteJson("/api/vehicles/{$this->vehicle2->id}");

        $response->assertNotFound();
    }

    public function test_superadmin_can_delete_any_vehicle(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/vehicles/{$this->vehicle2->id}");

        $response->assertOk();
        $this->assertSoftDeleted('vehicles', ['id' => $this->vehicle2->id]);
    }
}
