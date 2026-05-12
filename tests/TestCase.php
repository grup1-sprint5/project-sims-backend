<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Neteja la caché de permisos de Spatie abans de cada test.
     * Necessari quan s'usa RefreshDatabase per evitar que els permisos
     * de la iteració anterior quedin en memòria i provoquin errors.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy')) {
            try {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            } catch (\Throwable) {
                // Keep PHPUnit teardown focused on rolling back the test database.
            }
        }

        parent::tearDown();
    }
}
