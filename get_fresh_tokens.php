<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = [
    'superadmin' => User::role('SuperAdmin')->first(),
    'client' => User::role('Client')->first(),
    'tenantworker' => User::role('TenantWorker')->first(),
];

foreach ($users as $role => $user) {
    if ($user) {
        $token = $user->createToken('AuditToken-' . now()->timestamp)->plainTextToken;
        echo strtoupper($role) . " TOKEN: $token\n";
    } else {
        echo strtoupper($role) . " USER NOT FOUND\n";
    }
}
