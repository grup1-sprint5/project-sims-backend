<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Ticket;
use App\Models\Reservation;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing users (created by DatabaseSeeder) — look up by email to avoid ID assumptions
        $admin  = User::where('email', 'admin@test.com')->first();
        $client = User::where('email', 'client@test.com')->first();
        $maint  = User::where('email', 'maint@test.com')->first();

        // Skip if test users haven't been created yet (DatabaseSeeder must run first)
        if (!$admin || !$client || !$maint) {
            echo "⚠️  Test users not found — run DatabaseSeeder first. Skipping TestDataSeeder.\n";
            return;
        }

        // Reuse real vehicles if they already exist; only create missing ones for tests
        $vehicles = Vehicle::query()->orderBy('id')->take(3)->get();

        while ($vehicles->count() < 3) {
            $suffix = strtoupper(substr(uniqid(), -6));
            $newVehicle = Vehicle::create([
                'license_plate' => $suffix,
                'brand' => 'Test',
                'model' => 'Vehicle',
                'active' => false,
            ]);
            $vehicles->push($newVehicle);
        }

        $vehicle1 = $vehicles->get(0);
        $vehicle2 = $vehicles->get(1);
        $vehicle3 = $vehicles->get(2);

        // Create test tickets
        Ticket::create([
            'user_id' => $client->id,
            'title' => 'Ticket de cliente 1',
            'description' => 'Problema con el vehículo',
            'active' => true,
        ]);

        Ticket::create([
            'user_id' => $client->id,
            'title' => 'Ticket de cliente 2',
            'description' => 'Solicitud de servicio',
            'active' => true,
        ]);

        Ticket::create([
            'user_id' => $admin->id,
            'title' => 'Ticket del admin',
            'description' => 'Mantenimiento',
            'active' => true,
        ]);

        // Create test reservations
        Reservation::create([
            'user_id' => $client->id,
            'vehicle_id' => $vehicle1->id,
            'scheduled_start' => Carbon::now()->addHours(2),
            'activation_deadline' => Carbon::now()->addHours(2)->addMinutes(20),
            'status' => 'pending',
        ]);

        Reservation::create([
            'user_id' => $client->id,
            'vehicle_id' => $vehicle2->id,
            'scheduled_start' => Carbon::now()->addDays(1),
            'activation_deadline' => Carbon::now()->addDays(1)->addMinutes(20),
            'status' => 'active',
        ]);

        Reservation::create([
            'user_id' => $admin->id,
            'vehicle_id' => $vehicle3->id,
            'scheduled_start' => Carbon::now()->subHours(5),
            'activation_deadline' => Carbon::now()->subHours(5)->addMinutes(20),
            'status' => 'completed',
        ]);

        echo "✅ Test data created!\n";
    }
}
