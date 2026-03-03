<?php

namespace Tests\Feature\MultiTenancy;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationIsolationTest extends TestCase
{
    use RefreshDatabase, TenantTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantData();
    }

    // ──────────────────────────────────────
    // GET /api/reservations (index)
    // ──────────────────────────────────────

    public function test_superadmin_sees_all_reservations(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/reservations');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_tenant_admin_only_sees_own_tenant_reservations(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/reservations');

        $response->assertOk();
        $reservations = $response->json();
        $this->assertCount(1, $reservations);
        $this->assertEquals($this->reservation1->id, $reservations[0]['id']);
    }

    public function test_tenant_admin_does_not_see_other_tenant_reservations(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/reservations');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($this->reservation2->id));
    }

    public function test_client_only_sees_own_reservations(): void
    {
        $response = $this->actingAs($this->client1)
            ->getJson('/api/reservations');

        $response->assertOk();
        $reservations = $response->json();
        $this->assertCount(1, $reservations);
        $this->assertEquals($this->reservation1->id, $reservations[0]['id']);
    }

    // ──────────────────────────────────────
    // GET /api/reservations/{id} (show)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_view_own_tenant_reservation(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/reservations/{$this->reservation1->id}");

        $response->assertOk();
    }

    public function test_tenant_admin_cannot_view_other_tenant_reservation(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/reservations/{$this->reservation2->id}");

        $response->assertNotFound();
    }

    public function test_client_can_view_own_reservation(): void
    {
        $response = $this->actingAs($this->client1)
            ->getJson("/api/reservations/{$this->reservation1->id}");

        $response->assertOk();
    }

    public function test_client_cannot_view_other_tenant_reservation(): void
    {
        $response = $this->actingAs($this->client1)
            ->getJson("/api/reservations/{$this->reservation2->id}");

        $response->assertNotFound();
    }

    // ──────────────────────────────────────
    // POST /api/reservations (store)
    // ──────────────────────────────────────

    public function test_client_creates_reservation_with_own_tenant_id(): void
    {
        // Create a new available vehicle for tenant1
        $newVehicle = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant1->id,
            'license_plate' => '3333-RES',
            'brand' => 'Seat',
            'model' => 'Leon',
            'active' => true,
        ]);

        $response = $this->actingAs($this->client1)
            ->postJson('/api/reservations', [
                'vehicle_id' => $newVehicle->id,
                'scheduled_start' => now()->addHours(2)->toISOString(),
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('reservations', [
            'vehicle_id' => $newVehicle->id,
            'user_id' => $this->client1->id,
            'tenant_id' => $this->tenant1->id,
        ]);
    }

    // ──────────────────────────────────────
    // POST /api/reservations/{id}/activate
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_activate_other_tenant_reservation(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->postJson("/api/reservations/{$this->reservation2->id}/activate");

        $response->assertNotFound();
    }

    // ──────────────────────────────────────
    // POST /api/reservations/{id}/cancel
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_cancel_other_tenant_reservation(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->postJson("/api/reservations/{$this->reservation2->id}/cancel");

        $response->assertNotFound();
    }

    // ──────────────────────────────────────
    // Admin endpoint isolation
    // ──────────────────────────────────────

    public function test_tenant_admin_only_sees_own_reservations_in_admin_endpoint(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/admin/reservations');

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $ids = collect($data)->pluck('id');
        $this->assertTrue($ids->contains($this->reservation1->id));
        $this->assertFalse($ids->contains($this->reservation2->id));
    }

    public function test_superadmin_sees_all_reservations_in_admin_endpoint(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/reservations');

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $this->assertCount(2, $data);
    }
}
