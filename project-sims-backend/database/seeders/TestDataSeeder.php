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
        // Get existing users (created by DatabaseSeeder)
        // ID 1 = Admin, ID 2 = Client, ID 3 = Maintenance
        $admin = User::find(1);      // Admin
        $client = User::find(2);     // Client
        $maint = User::find(3);      // Maintenance

        // Create vehicles that match MongoVehicleLocationsSeeder (use updateOrCreate to avoid duplicates)
        $vehicle1 = Vehicle::updateOrCreate(
            ['license_plate' => 'ABC123'],
            ['brand' => 'Toyota', 'model' => 'Yaris', 'active' => true]
        );

        $vehicle2 = Vehicle::updateOrCreate(
            ['license_plate' => 'DEF456'],
            ['brand' => 'Ford', 'model' => 'Fiesta', 'active' => false]
        );

        $vehicle3 = Vehicle::updateOrCreate(
            ['license_plate' => 'GHI789'],
            ['brand' => 'Nissan', 'model' => 'March', 'active' => true]
        );

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
