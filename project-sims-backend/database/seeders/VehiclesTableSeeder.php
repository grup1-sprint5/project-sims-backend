<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehiclesTableSeeder extends Seeder
{
    public function run()
    {
        $vehicles = [
            // Mark ABC123 as inactive (available for reservation)
            ['license_plate' => 'ABC123', 'brand' => 'Toyota', 'model' => 'Yaris', 'active' => false],
            // Other vehicles busy
            ['license_plate' => 'DEF456', 'brand' => 'Ford', 'model' => 'Fiesta', 'active' => true],
            ['license_plate' => 'GHI789', 'brand' => 'Nissan', 'model' => 'March', 'active' => true],
        ];

        foreach ($vehicles as $v) {
            DB::table('vehicles')->updateOrInsert(
                ['license_plate' => $v['license_plate']],
                [
                    'brand' => $v['brand'],
                    'model' => $v['model'],
                    'active' => $v['active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
