<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class EnsureJordiAdminSeeder extends Seeder
{
    public function run()
    {
        $role = Role::firstOrCreate(['name' => 'SuperAdmin']);

        $user = User::firstOrCreate(
            ['email' => 'jordi@jordi.com'],
            [
                'name' => 'Jordi',
                'username' => 'jordi',
                'password' => bcrypt('SuperAdmin123!'),
                'active' => true,
            ]
        );

        if (!$user->hasRole('SuperAdmin')) {
            $user->assignRole($role);
        }

        $user->active = true;
        $user->save();

        echo "Ensured admin user jordi@jordi.com\n";
    }
}
