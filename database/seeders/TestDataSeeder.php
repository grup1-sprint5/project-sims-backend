<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Ticket;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = function_exists('tenant') && tenant() ? (string) tenant('id') : null;
        if (!$tenantId) {
            echo "⚠️  No tenant context initialized. Skipping TestDataSeeder.\n";
            return;
        }

        $admin = User::role('TenantAdmin')->where('tenant_id', $tenantId)->first();
        $workers = User::role('TenantWorker')->where('tenant_id', $tenantId)->orderBy('id')->take(2)->get();
        $clients = User::role('Client')->where('tenant_id', $tenantId)->orderBy('id')->take(3)->get();

        if (!$admin || $workers->count() < 2 || $clients->count() < 3) {
            echo "⚠️  Required tenant users not found. Run TenantDatabaseSeeder first. Skipping TestDataSeeder.\n";
            return;
        }

        $worker1 = $workers->values()->get(0);
        $worker2 = $workers->values()->get(1);
        $client1 = $clients->values()->get(0);
        $client2 = $clients->values()->get(1);
        $client3 = $clients->values()->get(2);

        DB::statement('TRUNCATE TABLE trips, reservations, ticket_messages, tickets, vehicles RESTART IDENTITY CASCADE');

        if ($tenantId === 'sims-corp') {
            $v1 = Vehicle::updateOrCreate(['license_plate' => 'ABC123'], ['brand' => 'Toyota', 'model' => 'Yaris', 'active' => true, 'tenant_id' => $tenantId]);
            $v2 = Vehicle::updateOrCreate(['license_plate' => 'DEF456'], ['brand' => 'Ford', 'model' => 'Fiesta', 'active' => false, 'tenant_id' => $tenantId]);
            $v3 = Vehicle::updateOrCreate(['license_plate' => '1234BCD'], ['brand' => 'Seat', 'model' => 'Ibiza', 'active' => true, 'tenant_id' => $tenantId]);
            $v4 = Vehicle::updateOrCreate(['license_plate' => '5678FGH'], ['brand' => 'Renault', 'model' => 'Clio', 'active' => true, 'tenant_id' => $tenantId]);
            $v5 = Vehicle::updateOrCreate(['license_plate' => '9012JKL'], ['brand' => 'Volkswagen', 'model' => 'Polo', 'active' => false, 'tenant_id' => $tenantId]);

            Ticket::create(['user_id' => $client1->id, 'title' => 'Problema con el arranque', 'description' => 'El vehículo ABC123 no arranca correctamente por la mañana', 'active' => true, 'tenant_id' => $tenantId]);
            Ticket::create(['user_id' => $client2->id, 'title' => 'Solicitud de factura', 'description' => 'Necesito la factura del mes pasado', 'active' => true, 'tenant_id' => $tenantId]);
            Ticket::create(['user_id' => $worker1->id, 'title' => 'Revisión de flota', 'description' => 'Detectados avisos de mantenimiento en dos vehículos', 'active' => false, 'tenant_id' => $tenantId]);

            Reservation::create(['user_id' => $client1->id, 'vehicle_id' => $v1->id, 'scheduled_start' => Carbon::now()->addHours(2), 'activation_deadline' => Carbon::now()->addHours(2)->addMinutes(20), 'status' => 'pending', 'tenant_id' => $tenantId]);
            Reservation::create(['user_id' => $client2->id, 'vehicle_id' => $v3->id, 'scheduled_start' => Carbon::now()->addHours(5), 'activation_deadline' => Carbon::now()->addHours(5)->addMinutes(20), 'status' => 'pending', 'tenant_id' => $tenantId]);
            Reservation::create(['user_id' => $client3->id, 'vehicle_id' => $v4->id, 'scheduled_start' => Carbon::now()->subHours(3), 'activation_deadline' => Carbon::now()->subHours(3)->addMinutes(20), 'status' => 'active', 'tenant_id' => $tenantId]);
            Reservation::create(['user_id' => $client1->id, 'vehicle_id' => $v2->id, 'scheduled_start' => Carbon::now()->subDays(2), 'activation_deadline' => Carbon::now()->subDays(2)->addMinutes(20), 'status' => 'completed', 'tenant_id' => $tenantId]);
        }

        if ($tenantId === 'ecomove') {
            $v1 = Vehicle::updateOrCreate(['license_plate' => '3456MNP'], ['brand' => 'Tesla', 'model' => 'Model 3', 'active' => true, 'tenant_id' => $tenantId]);
            $v2 = Vehicle::updateOrCreate(['license_plate' => '7890QRS'], ['brand' => 'BMW', 'model' => 'i3', 'active' => true, 'tenant_id' => $tenantId]);
            $v3 = Vehicle::updateOrCreate(['license_plate' => '2345TUV'], ['brand' => 'Hyundai', 'model' => 'Kona EV', 'active' => false, 'tenant_id' => $tenantId]);
            $v4 = Vehicle::updateOrCreate(['license_plate' => '6789WXY'], ['brand' => 'Peugeot', 'model' => 'e-208', 'active' => true, 'tenant_id' => $tenantId]);

            Ticket::create(['user_id' => $client1->id, 'title' => 'Batería baja en Tesla', 'description' => 'El Model 3 con matrícula 3456MNP tiene la batería al 5% en zona sin cargador', 'active' => true, 'tenant_id' => $tenantId]);
            Ticket::create(['user_id' => $client2->id, 'title' => 'Error al finalizar viaje', 'description' => 'No puedo finalizar el viaje, la app muestra un error 500', 'active' => true, 'tenant_id' => $tenantId]);
            Ticket::create(['user_id' => $worker2->id, 'title' => 'Revisión BMW i3', 'description' => 'Revisión programada completada para el BMW i3', 'active' => false, 'tenant_id' => $tenantId]);

            Reservation::create(['user_id' => $client1->id, 'vehicle_id' => $v1->id, 'scheduled_start' => Carbon::now()->addHours(1), 'activation_deadline' => Carbon::now()->addHours(1)->addMinutes(20), 'status' => 'pending', 'tenant_id' => $tenantId]);
            Reservation::create(['user_id' => $client2->id, 'vehicle_id' => $v2->id, 'scheduled_start' => Carbon::now()->subHours(1), 'activation_deadline' => Carbon::now()->subHours(1)->addMinutes(20), 'status' => 'active', 'tenant_id' => $tenantId]);
            Reservation::create(['user_id' => $client1->id, 'vehicle_id' => $v4->id, 'scheduled_start' => Carbon::now()->subDays(1), 'activation_deadline' => Carbon::now()->subDays(1)->addMinutes(20), 'status' => 'completed', 'tenant_id' => $tenantId]);
            Reservation::create(['user_id' => $client3->id, 'vehicle_id' => $v3->id, 'scheduled_start' => Carbon::now()->subDays(3), 'activation_deadline' => Carbon::now()->subDays(3)->addMinutes(20), 'status' => 'cancelled', 'tenant_id' => $tenantId]);
        }

        echo "✅ Test data created!\n";
    }
}
