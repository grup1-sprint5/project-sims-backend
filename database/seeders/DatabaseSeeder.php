<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Cargar Permisos y Roles primero
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
        ]);

        $password = Hash::make('password'); // Contraseña común para test

        // 2. Crear ADMIN (El Jefe)
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'], // Cambiado a test.com para uniformidad
            [
                'name' => 'Super Admin',
                'username' => 'admin',
                'password' => $password,
                'active' => true,
            ]
        );
        $admin->assignRole('Admin');

        // 3. Crear CLIENTE (El usuario estándar)
        $client = User::firstOrCreate(
            ['email' => 'client@test.com'],
            [
                'name' => 'Cliente de Prueba',
                'username' => 'client',
                'password' => $password,
                'active' => true,
            ]
        );
        $client->assignRole('Client');

        // 4. Crear MANTENIMIENTO (El técnico)
        $maintenance = User::firstOrCreate(
            ['email' => 'maint@test.com'],
            [
                'name' => 'Técnico Mantenimiento',
                'username' => 'maintenance',
                'password' => $password,
                'active' => true,
            ]
        );
        $maintenance->assignRole('Maintenance');

        // 5. Crear datos de prueba
        $this->call([
            TestDataSeeder::class,
        ]);
    }
}