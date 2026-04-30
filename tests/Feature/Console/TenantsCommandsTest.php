<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenants_list_command_displays_all_tenants(): void
    {
        $tenant1 = Tenant::create([
            'id' => 'list-test-1',
            'name' => 'Test Tenant 1',
            'email' => 'test1@example.com',
            'active' => true,
        ]);

        $tenant2 = Tenant::create([
            'id' => 'list-test-2',
            'name' => 'Test Tenant 2',
            'email' => 'test2@example.com',
            'active' => false,
        ]);

        $this->artisan('tenants:list')
            ->expectsOutputToContain('list-test-1')
            ->expectsOutputToContain('Test Tenant 1')
            ->expectsOutputToContain('test1@example.com')
            ->expectsOutputToContain('list-test-2')
            ->expectsOutputToContain('Test Tenant 2')
            ->expectsOutputToContain('test2@example.com')
            ->assertExitCode(0);
    }

    public function test_tenants_list_json_format(): void
    {
        Tenant::create([
            'id' => 'json-test',
            'name' => 'JSON Test Tenant',
            'email' => 'json@example.com',
            'active' => true,
        ]);

        $this->artisan('tenants:list', ['--format' => 'json'])
            ->expectsOutputToContain('"id"')
            ->expectsOutputToContain('"json-test"')
            ->expectsOutputToContain('"name"')
            ->expectsOutputToContain('"JSON Test Tenant"')
            ->assertExitCode(0);
    }

    public function test_tenants_list_empty_database(): void
    {
        $this->artisan('tenants:list')
            ->expectsOutput('No tenants found.')
            ->assertExitCode(0);
    }

    public function test_check_integrity_reports_missing_schemas(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Schema integrity checks are only supported on PostgreSQL.');
        }

        // Create a tenant but don't initialize it (so no schema exists)
        Tenant::create([
            'id' => 'integrity-test',
            'name' => 'Integrity Test',
            'email' => 'integrity@example.com',
            'active' => true,
        ]);

        $output = $this->artisan('tenants:check-integrity')
            ->output();

        // Should report schema as missing
        $this->assertStringContainsString('integrity-test', $output);
        $this->assertStringContainsString('does not exist', $output);
    }

    public function test_check_integrity_validates_existing_schemas(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Schema integrity checks are only supported on PostgreSQL.');
        }

        $tenant = Tenant::create([
            'id' => 'valid-integrity-test',
            'name' => 'Valid Integrity Test',
            'email' => 'valid@example.com',
            'active' => true,
        ]);

        // Initialize to create schema
        tenancy()->initialize($tenant);
        tenancy()->end();

        $output = $this->artisan('tenants:check-integrity')
            ->output();

        // Should show schema exists
        $this->assertStringContainsString('valid-integrity-test', $output);
        $this->assertStringContainsString('Schema exists', $output);
    }

    public function test_check_integrity_empty_database(): void
    {
        $this->artisan('tenants:check-integrity')
            ->expectsOutput('No tenants to check.')
            ->assertExitCode(0);
    }
}
