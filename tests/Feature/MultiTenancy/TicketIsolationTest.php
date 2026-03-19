<?php

namespace Tests\Feature\MultiTenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketIsolationTest extends TestCase
{
    use RefreshDatabase, TenantTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantData();
    }

    // ──────────────────────────────────────
    // GET /api/tickets (index)
    // ──────────────────────────────────────

    public function test_superadmin_sees_all_tickets(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/tickets');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_tenant_admin_only_sees_own_tenant_tickets(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson('/api/tickets');

        $response->assertOk();
        $tickets = $response->json();
        $this->assertCount(1, $tickets);
        $this->assertEquals($this->ticket1->id, $tickets[0]['id']);
    }

    public function test_tenant_admin_does_not_see_other_tenant_tickets(): void
    {
        $response = $this->actingAs($this->tenantAdmin2)
            ->getJson('/api/tickets');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($this->ticket1->id));
    }

    public function test_client_only_sees_own_tickets(): void
    {
        $response = $this->actingAs($this->client1)
            ->getJson('/api/tickets');

        $response->assertOk();
        $tickets = $response->json();
        $this->assertCount(1, $tickets);
        $this->assertEquals($this->ticket1->id, $tickets[0]['id']);
    }

    public function test_client_does_not_see_other_client_tickets(): void
    {
        $response = $this->actingAs($this->client1)
            ->getJson('/api/tickets');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertFalse($ids->contains($this->ticket2->id));
    }

    // ──────────────────────────────────────
    // GET /api/tickets/{id} (show)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_view_own_tenant_ticket(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/tickets/{$this->ticket1->id}");

        $response->assertOk();
    }

    public function test_tenant_admin_cannot_view_other_tenant_ticket(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->getJson("/api/tickets/{$this->ticket2->id}");

        $response->assertNotFound();
    }

    public function test_client_can_view_own_ticket(): void
    {
        $response = $this->actingAs($this->client1)
            ->getJson("/api/tickets/{$this->ticket1->id}");

        $response->assertOk();
    }

    public function test_client_cannot_view_other_tenant_ticket(): void
    {
        $response = $this->actingAs($this->client1)
            ->getJson("/api/tickets/{$this->ticket2->id}");

        $response->assertNotFound();
    }

    // ──────────────────────────────────────
    // POST /api/tickets (store)
    // ──────────────────────────────────────

    public function test_tenant_admin_creates_ticket_with_own_tenant_id(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->postJson('/api/tickets', [
                'title' => 'Nuevo ticket SIMS',
                'description' => 'Creado por admin SIMS',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tickets', [
            'title' => 'Nuevo ticket SIMS',
            'tenant_id' => $this->tenant1->id,
        ]);
    }

    public function test_client_creates_ticket_with_own_tenant_id(): void
    {
        $response = $this->actingAs($this->client1)
            ->postJson('/api/tickets', [
                'title' => 'Ticket de cliente',
                'description' => 'Problema con el vehículo',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tickets', [
            'title' => 'Ticket de cliente',
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->client1->id,
        ]);
    }

    // ──────────────────────────────────────
    // PUT /api/tickets/{id} (update)
    // ──────────────────────────────────────

    public function test_tenant_admin_can_update_own_tenant_ticket(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/tickets/{$this->ticket1->id}", [
                'title' => 'Ticket actualizado',
            ]);

        $response->assertOk();
    }

    public function test_tenant_admin_cannot_update_other_tenant_ticket(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->putJson("/api/tickets/{$this->ticket2->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertNotFound();
    }

    // ──────────────────────────────────────
    // DELETE /api/tickets/{id} (destroy)
    // ──────────────────────────────────────

    public function test_tenant_admin_cannot_delete_other_tenant_ticket(): void
    {
        $response = $this->actingAs($this->tenantAdmin1)
            ->deleteJson("/api/tickets/{$this->ticket2->id}");

        $response->assertNotFound();
    }

    public function test_superadmin_can_delete_any_ticket(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/tickets/{$this->ticket2->id}");

        $response->assertNoContent();
    }
}
