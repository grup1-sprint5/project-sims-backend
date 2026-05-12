<?php

namespace Tests\Feature\MultiTenancy;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase, TenantTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantData();
    }

    // ──────────────────────────────────────
    // Global Scope: Query filtering
    // ──────────────────────────────────────
    // Listing and basic isolation for models is covered by API Isolation tests.
    // Keep model-specific behavior tests below (auto-assignment, scopes, find protection).

    // ──────────────────────────────────────
    // Auto-assignment: tenant_id on creation
    // ──────────────────────────────────────

    public function test_tenant_admin_creating_vehicle_auto_assigns_tenant_id(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $vehicle = Vehicle::create([
            'license_plate' => '9999-ZZZ',
            'brand' => 'Ford',
            'model' => 'Fiesta',
            'active' => true,
        ]);

        $this->assertEquals($this->tenant1->id, $vehicle->tenant_id);
    }

    public function test_superadmin_creating_vehicle_does_not_auto_assign_tenant_id(): void
    {
        $this->actingAs($this->superAdmin);

        $vehicle = Vehicle::create([
            'license_plate' => '8888-YYY',
            'brand' => 'BMW',
            'model' => 'i3',
            'active' => true,
        ]);

        $this->assertNull($vehicle->tenant_id);
    }

    public function test_tenant_admin_creating_ticket_auto_assigns_tenant_id(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $ticket = \App\Models\Ticket::create([
            'user_id' => $this->tenantAdmin1->id,
            'title' => 'Test Ticket',
            'description' => 'Created by TenantAdmin',
            'active' => true,
        ]);

        $this->assertEquals($this->tenant1->id, $ticket->tenant_id);
    }

    // ──────────────────────────────────────
    // withoutGlobalScopes bypass
    // ──────────────────────────────────────

    public function test_without_global_scopes_returns_all_records(): void
    {
        $this->actingAs($this->tenantAdmin1);

        // With scope: only own tenant
        $scoped = Vehicle::all();
        $this->assertCount(1, $scoped);

        // Without scope: all records
        $all = Vehicle::withoutGlobalScopes()->get();
        $this->assertCount(2, $all);
    }

    // ──────────────────────────────────────
    // Cross-tenant find protection
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_find_other_tenant_vehicle_by_id(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $vehicle = Vehicle::find($this->vehicle2->id);

        $this->assertNull($vehicle);
    }

    public function test_tenant_admin_cannot_find_other_tenant_user_by_id(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $user = \App\Models\User::find($this->client2->id);

        $this->assertNull($user);
    }

    public function test_tenant_admin_cannot_find_other_tenant_ticket_by_id(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $ticket = \App\Models\Ticket::find($this->ticket2->id);

        $this->assertNull($ticket);
    }

    public function test_tenant_admin_cannot_find_other_tenant_reservation_by_id(): void
    {
        $this->actingAs($this->tenantAdmin1);

        $reservation = \App\Models\Reservation::find($this->reservation2->id);

        $this->assertNull($reservation);
    }
}
