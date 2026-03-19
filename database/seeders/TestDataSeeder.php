<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Ticket;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        // Get tenants
        $simsTenant = \App\Models\Tenant::find('sims-corp');
        $ecoTenant = \App\Models\Tenant::find('ecomove');

        // Get existing users (created by DatabaseSeeder) — look up by email to avoid ID assumptions
        $admin  = User::where('email', 'admin@test.com')->first() ?: User::find(1);
        $client = User::where('email', 'client@test.com')->first() ?: User::find(2);
        $maint  = User::where('email', 'maint@test.com')->first() ?: User::find(3);

        // Skip if test users haven't been created yet (DatabaseSeeder must run first)
        if (!$admin || !$client || !$maint) {
            echo "⚠️  Test users not found — run DatabaseSeeder first. Skipping TestDataSeeder.\n";
            return;
        }

        // Assign existing users to tenants
        if ($client && $simsTenant) {
            $client->update(['tenant_id' => $simsTenant->id]);
        }
        if ($maint && $ecoTenant) {
            $maint->update(['tenant_id' => $ecoTenant->id]);
        }

        // ─── Additional users for SIMS Corp ───
        $simsClient2 = User::firstOrCreate(
            ['email' => 'maria@simscorp.com'],
            ['name' => 'María García', 'username' => 'maria_sims', 'password' => $password, 'active' => true, 'tenant_id' => $simsTenant?->id]
        );
        $simsClient2->assignRole('Client');

        $simsClient3 = User::firstOrCreate(
            ['email' => 'carlos@simscorp.com'],
            ['name' => 'Carlos López', 'username' => 'carlos_sims', 'password' => $password, 'active' => true, 'tenant_id' => $simsTenant?->id]
        );
        $simsClient3->assignRole('Client');

        $simsMaint = User::firstOrCreate(
            ['email' => 'tecnico@simscorp.com'],
            ['name' => 'Pedro Martínez', 'username' => 'pedro_sims', 'password' => $password, 'active' => true, 'tenant_id' => $simsTenant?->id]
        );
        $simsMaint->assignRole('Maintenance');

        // ─── Additional users for EcoMove ───
        $ecoClient1 = User::firstOrCreate(
            ['email' => 'laura@ecomove.es'],
            ['name' => 'Laura Fernández', 'username' => 'laura_eco', 'password' => $password, 'active' => true, 'tenant_id' => $ecoTenant?->id]
        );
        $ecoClient1->assignRole('Client');

        $ecoClient2 = User::firstOrCreate(
            ['email' => 'jorge@ecomove.es'],
            ['name' => 'Jorge Ruiz', 'username' => 'jorge_eco', 'password' => $password, 'active' => true, 'tenant_id' => $ecoTenant?->id]
        );
        $ecoClient2->assignRole('Client');

        $ecoClient3 = User::firstOrCreate(
            ['email' => 'ana@ecomove.es'],
            ['name' => 'Ana Sánchez', 'username' => 'ana_eco', 'password' => $password, 'active' => false, 'tenant_id' => $ecoTenant?->id]
        );
        $ecoClient3->assignRole('Client');

        // ─── Vehicles for SIMS Corp ───
        $v1 = Vehicle::updateOrCreate(
            ['license_plate' => 'ABC123'],
            ['brand' => 'Toyota', 'model' => 'Yaris', 'active' => true, 'tenant_id' => $simsTenant?->id]
        );
        $v2 = Vehicle::updateOrCreate(
            ['license_plate' => 'DEF456'],
            ['brand' => 'Ford', 'model' => 'Fiesta', 'active' => false, 'tenant_id' => $simsTenant?->id]
        );
        $v3 = Vehicle::updateOrCreate(
            ['license_plate' => '1234BCD'],
            ['brand' => 'Seat', 'model' => 'Ibiza', 'active' => true, 'tenant_id' => $simsTenant?->id]
        );
        $v4 = Vehicle::updateOrCreate(
            ['license_plate' => '5678FGH'],
            ['brand' => 'Renault', 'model' => 'Clio', 'active' => true, 'tenant_id' => $simsTenant?->id]
        );
        $v5 = Vehicle::updateOrCreate(
            ['license_plate' => '9012JKL'],
            ['brand' => 'Volkswagen', 'model' => 'Polo', 'active' => false, 'tenant_id' => $simsTenant?->id]
        );

        // ─── Vehicles for EcoMove ───
        $v6 = Vehicle::updateOrCreate(
            ['license_plate' => 'GHI789'],
            ['brand' => 'Nissan', 'model' => 'Leaf', 'active' => true, 'tenant_id' => $ecoTenant?->id]
        );
        $v7 = Vehicle::updateOrCreate(
            ['license_plate' => '3456MNP'],
            ['brand' => 'Tesla', 'model' => 'Model 3', 'active' => true, 'tenant_id' => $ecoTenant?->id]
        );
        $v8 = Vehicle::updateOrCreate(
            ['license_plate' => '7890QRS'],
            ['brand' => 'BMW', 'model' => 'i3', 'active' => true, 'tenant_id' => $ecoTenant?->id]
        );
        $v9 = Vehicle::updateOrCreate(
            ['license_plate' => '2345TUV'],
            ['brand' => 'Hyundai', 'model' => 'Kona EV', 'active' => false, 'tenant_id' => $ecoTenant?->id]
        );
        $v10 = Vehicle::updateOrCreate(
            ['license_plate' => '6789WXY'],
            ['brand' => 'Peugeot', 'model' => 'e-208', 'active' => true, 'tenant_id' => $ecoTenant?->id]
        );

        // ─── Tickets for SIMS Corp ───
        Ticket::create([
            'user_id' => $client->id,
            'title' => 'Problema con el arranque',
            'description' => 'El vehículo ABC123 no arranca correctamente por la mañana',
            'active' => true,
            'tenant_id' => $simsTenant?->id,
        ]);
        Ticket::create([
            'user_id' => $simsClient2->id,
            'title' => 'Solicitud de factura',
            'description' => 'Necesito la factura del mes pasado',
            'active' => true,
            'tenant_id' => $simsTenant?->id,
        ]);
        Ticket::create([
            'user_id' => $simsClient3->id,
            'title' => 'App no carga el mapa',
            'description' => 'La aplicación se queda en blanco al abrir el mapa de vehículos',
            'active' => false,
            'tenant_id' => $simsTenant?->id,
        ]);

        // ─── Tickets for EcoMove ───
        Ticket::create([
            'user_id' => $ecoClient1->id,
            'title' => 'Batería baja en Tesla',
            'description' => 'El Model 3 con matrícula 3456MNP tiene la batería al 5% en zona sin cargador',
            'active' => true,
            'tenant_id' => $ecoTenant?->id,
        ]);
        Ticket::create([
            'user_id' => $ecoClient2->id,
            'title' => 'Error al finalizar viaje',
            'description' => 'No puedo finalizar el viaje, la app muestra un error 500',
            'active' => true,
            'tenant_id' => $ecoTenant?->id,
        ]);
        Ticket::create([
            'user_id' => $maint->id,
            'title' => 'Mantenimiento BMW i3',
            'description' => 'Revisión programada completada para el BMW i3',
            'active' => false,
            'tenant_id' => $ecoTenant?->id,
        ]);

        // ─── Reservations for SIMS Corp ───
        Reservation::create([
            'user_id' => $client->id,
            'vehicle_id' => $v1->id,
            'scheduled_start' => Carbon::now()->addHours(2),
            'activation_deadline' => Carbon::now()->addHours(2)->addMinutes(20),
            'status' => 'pending',
            'tenant_id' => $simsTenant?->id,
        ]);
        Reservation::create([
            'user_id' => $simsClient2->id,
            'vehicle_id' => $v3->id,
            'scheduled_start' => Carbon::now()->addHours(5),
            'activation_deadline' => Carbon::now()->addHours(5)->addMinutes(20),
            'status' => 'pending',
            'tenant_id' => $simsTenant?->id,
        ]);
        Reservation::create([
            'user_id' => $simsClient3->id,
            'vehicle_id' => $v4->id,
            'scheduled_start' => Carbon::now()->subHours(3),
            'activation_deadline' => Carbon::now()->subHours(3)->addMinutes(20),
            'status' => 'active',
            'tenant_id' => $simsTenant?->id,
        ]);
        Reservation::create([
            'user_id' => $client->id,
            'vehicle_id' => $v2->id,
            'scheduled_start' => Carbon::now()->subDays(2),
            'activation_deadline' => Carbon::now()->subDays(2)->addMinutes(20),
            'status' => 'completed',
            'tenant_id' => $simsTenant?->id,
        ]);

        // ─── Reservations for EcoMove ───
        Reservation::create([
            'user_id' => $ecoClient1->id,
            'vehicle_id' => $v6->id,
            'scheduled_start' => Carbon::now()->addHours(1),
            'activation_deadline' => Carbon::now()->addHours(1)->addMinutes(20),
            'status' => 'pending',
            'tenant_id' => $ecoTenant?->id,
        ]);
        Reservation::create([
            'user_id' => $ecoClient2->id,
            'vehicle_id' => $v7->id,
            'scheduled_start' => Carbon::now()->subHours(1),
            'activation_deadline' => Carbon::now()->subHours(1)->addMinutes(20),
            'status' => 'active',
            'tenant_id' => $ecoTenant?->id,
        ]);
        Reservation::create([
            'user_id' => $ecoClient1->id,
            'vehicle_id' => $v8->id,
            'scheduled_start' => Carbon::now()->subDays(1),
            'activation_deadline' => Carbon::now()->subDays(1)->addMinutes(20),
            'status' => 'completed',
            'tenant_id' => $ecoTenant?->id,
        ]);
        Reservation::create([
            'user_id' => $ecoClient3->id,
            'vehicle_id' => $v10->id,
            'scheduled_start' => Carbon::now()->subDays(3),
            'activation_deadline' => Carbon::now()->subDays(3)->addMinutes(20),
            'status' => 'cancelled',
            'tenant_id' => $ecoTenant?->id,
        ]);

        echo "✅ Test data created!\n";
    }
}
