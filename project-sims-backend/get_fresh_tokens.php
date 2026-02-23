<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = [
    'admin' => User::role('admin')->first(),
    'client' => User::role('client')->first(),
    'maintenance' => User::role('maintenance')->first(),
];

foreach ($users as $role => $user) {
    if ($user) {
        $token = $user->createToken('AuditToken-' . now()->timestamp)->plainTextToken;
        echo strtoupper($role) . " TOKEN: $token\n";
    } else {
        echo strtoupper($role) . " USER NOT FOUND\n";
    }
}
