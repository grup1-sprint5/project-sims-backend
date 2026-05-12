<?php

namespace App\Listeners;

use Spatie\Permission\PermissionRegistrar;

class FlushPermissionCache
{
    public function handle(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
