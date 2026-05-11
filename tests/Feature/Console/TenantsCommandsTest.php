<?php

namespace Tests\Feature\Console;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenants_list_command_displays_all_tenants(): void
    {
        Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'list-test-1',
            'name' => 'Test Tenant 1',
            'email' => 'test1@example.com',
            'active' => true,
        ]));

        Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'list-test-2',
            'name' => 'Test Tenant 2',
            'email' => 'test2@example.com',
            'active' => false,
        ]));

        $exitCode = Artisan::call('tenants:list');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('list-test-1', $output);
        $this->assertStringContainsString('Test Tenant 1', $output);
        $this->assertStringContainsString('test1@example.com', $output);
        $this->assertStringContainsString('list-test-2', $output);
        $this->assertStringContainsString('Test Tenant 2', $output);
        $this->assertStringContainsString('test2@example.com', $output);
    }

    public function test_tenants_list_json_format(): void
    {
        Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'json-test',
            'name' => 'JSON Test Tenant',
            'email' => 'json@example.com',
            'active' => true,
        ]));

        $exitCode = Artisan::call('tenants:list', ['--format' => 'json']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"id"', $output);
        $this->assertStringContainsString('"json-test"', $output);
        $this->assertStringContainsString('"name"', $output);
        $this->assertStringContainsString('"JSON Test Tenant"', $output);
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

        $this->dropTenantSchema('integrity-test');

        // Create a tenant but don't trigger provisioning (so no schema exists).
        Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'integrity-test',
            'name' => 'Integrity Test',
            'email' => 'integrity@example.com',
            'active' => true,
        ]));

        $exitCode = Artisan::call('tenants:check-integrity');
        $output = Artisan::output();

        // Should report schema as missing
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('integrity-test', $output);
        $this->assertStringContainsString('does not exist', $output);
    }

    public function test_check_integrity_validates_existing_schemas(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Schema integrity checks are only supported on PostgreSQL.');
        }

        Tenant::withoutEvents(fn () => Tenant::create([
            'id' => 'valid-integrity-test',
            'name' => 'Valid Integrity Test',
            'email' => 'valid@example.com',
            'active' => true,
        ]));

        $this->dropTenantSchema('valid-integrity-test');
        DB::statement('CREATE SCHEMA "tenant_valid-integrity-test"');

        try {
            $exitCode = Artisan::call('tenants:check-integrity');
            $output = Artisan::output();
        } finally {
            $this->dropTenantSchema('valid-integrity-test');
        }

        // Should show schema exists
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('valid-integrity-test', $output);
        $this->assertStringContainsString('Schema exists', $output);
    }

    public function test_check_integrity_empty_database(): void
    {
        $this->artisan('tenants:check-integrity')
            ->expectsOutput('No tenants to check.')
            ->assertExitCode(0);
    }

    private function dropTenantSchema(string $tenantId): void
    {
        $schema = str_replace('"', '""', config('tenancy.database.prefix') . $tenantId);

        DB::statement(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schema));
    }
}
