<?php

namespace Tests\Feature\MultiTenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Ticket;
use App\Models\Reservation;
use App\Models\Trip;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Trait that provides multi-tenant test scaffolding.
 *
 * Creates:
 * - 2 tenants (SIMS Corp, EcoMove)
 * - 1 SuperAdmin (no tenant)
 * - 2 TenantAdmins (one per tenant)
 * - 2 Clients (one per tenant)
 * - Test data (vehicles, tickets, reservations) per tenant
 */
trait TenantTestHelpers
{
    protected Tenant $tenant1;
    protected Tenant $tenant2;

    protected User $superAdmin;
    protected User $tenantAdmin1;
    protected User $tenantAdmin2;
    protected User $client1;
    protected User $client2;

    // Test data
    protected Vehicle $vehicle1;
    protected Vehicle $vehicle2;
    protected Ticket $ticket1;
    protected Ticket $ticket2;
    protected Reservation $reservation1;
    protected Reservation $reservation2;

    protected function setUpTenantData(): void
    {
        // Reset Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Seed permissions and roles
        $this->seed(PermissionsSeeder::class);
        $this->seed(RolesSeeder::class);

        // Create tenants
        $this->tenant1 = Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'sims-corp',
            'name' => 'SIMS Corp',
            'tax_id' => 'B12345678',
            'email' => 'info@simscorp.com',
            'active' => true,
        ]));

        $this->tenant2 = Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'ecomove',
            'name' => 'EcoMove',
            'tax_id' => 'B87654321',
            'email' => 'info@ecomove.es',
            'active' => true,
        ]));

        // Create SuperAdmin (no tenant)
        $this->superAdmin = User::withoutGlobalScopes()->create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'super@test.com',
            'password' => 'password',
            'active' => true,
            'tenant_id' => null,
        ]);
        $this->superAdmin->syncRoles(['SuperAdmin']);

        // Create TenantAdmins
        $this->tenantAdmin1 = User::withoutGlobalScopes()->create([
            'name' => 'Admin SIMS',
            'username' => 'admin_sims',
            'email' => 'admin@simscorp.com',
            'password' => 'password',
            'active' => true,
            'tenant_id' => $this->tenant1->id,
        ]);
        $this->tenantAdmin1->syncRoles(['TenantAdmin']);

        $this->tenantAdmin2 = User::withoutGlobalScopes()->create([
            'name' => 'Admin EcoMove',
            'username' => 'admin_ecomove',
            'email' => 'admin@ecomove.es',
            'password' => 'password',
            'active' => true,
            'tenant_id' => $this->tenant2->id,
        ]);
        $this->tenantAdmin2->syncRoles(['TenantAdmin']);

        // Create Clients
        $this->client1 = User::withoutGlobalScopes()->create([
            'name' => 'Client SIMS',
            'username' => 'client_sims',
            'email' => 'client@simscorp.com',
            'password' => 'password',
            'active' => true,
            'tenant_id' => $this->tenant1->id,
        ]);
        $this->client1->syncRoles(['Client']);

        $this->client2 = User::withoutGlobalScopes()->create([
            'name' => 'Client EcoMove',
            'username' => 'client_ecomove',
            'email' => 'client@ecomove.es',
            'password' => 'password',
            'active' => true,
            'tenant_id' => $this->tenant2->id,
        ]);
        $this->client2->syncRoles(['Client']);

        // Create vehicles (one per tenant)
        $this->vehicle1 = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant1->id,
            'license_plate' => '1234-ABC',
            'brand' => 'Toyota',
            'model' => 'Yaris',
            'active' => true,
        ]);

        $this->vehicle2 = Vehicle::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant2->id,
            'license_plate' => '5678-DEF',
            'brand' => 'Nissan',
            'model' => 'Leaf',
            'active' => true,
        ]);

        // Create tickets (one per tenant)
        $this->ticket1 = Ticket::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->client1->id,
            'title' => 'Problema SIMS',
            'description' => 'Un problema del tenant SIMS',
            'active' => true,
        ]);

        $this->ticket2 = Ticket::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant2->id,
            'user_id' => $this->client2->id,
            'title' => 'Problema EcoMove',
            'description' => 'Un problema del tenant EcoMove',
            'active' => true,
        ]);

        // Create reservations (one per tenant)
        $this->reservation1 = Reservation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->client1->id,
            'vehicle_id' => $this->vehicle1->id,
            'scheduled_start' => now()->addHour(),
            'activation_deadline' => now()->addHour()->addMinutes(20),
            'status' => 'pending',
        ]);

        $this->reservation2 = Reservation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant2->id,
            'user_id' => $this->client2->id,
            'vehicle_id' => $this->vehicle2->id,
            'scheduled_start' => now()->addHour(),
            'activation_deadline' => now()->addHour()->addMinutes(20),
            'status' => 'pending',
        ]);
    }
}
