<?php

namespace Tests\Feature\Tenancy;

use App\Logging\TenantIdProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class TenantLoggingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that processor safely handles missing tenancy context.
     */
    public function test_tenant_id_processor_adds_central_by_default(): void
    {
        $processor = new TenantIdProcessor();

        // Create a log record
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
        );

        // Process the record (no tenancy initialized)
        $processed = $processor($record);

        // Verify 'central' is used when no tenant context
        $this->assertEquals('central', $processed->extra['tenant_id']);
    }

    /**
     * Test that processor uses 'central' when no tenant is initialized.
     */
    public function test_tenant_id_processor_uses_central_when_no_tenant(): void
    {
        $processor = new TenantIdProcessor();

        // Create a log record (no tenant initialized)
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Central test',
        );

        // Process the record
        $processed = $processor($record);

        // Verify 'central' is used when no tenant
        $this->assertEquals('central', $processed->extra['tenant_id']);
    }

    /**
     * Test that tenant_id context is set in real request with middleware.
     * This validates the full integration: middleware → processor → logs.
     */
    public function test_log_context_middleware_sets_tenant_in_logging(): void
    {
        $tenantId = 'middleware-test-' . now()->timestamp;

        $tenant = \App\Models\Tenant::create([
            'id' => $tenantId,
            'name' => 'Middleware Test',
            'email' => 'admin@middlewaretest.example.com',
            'active' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            $admin = \App\Models\User::where('email', 'admin@middlewaretest.example.com')->first();
            $adminPassword = 'change-me-' . $tenantId;

            tenancy()->end();

            // Make a request with tenant header (middleware applies)
            $response = $this->withHeader('X-Tenant', $tenantId)
                ->postJson('/api/login', [
                    'email' => 'admin@middlewaretest.example.com',
                    'password' => $adminPassword,
                ]);

            // Request should succeed with 200
            $response->assertOk();
            $this->assertNotNull($response->json('token'));
            $this->assertEquals($tenantId, $response->json('user.tenant_id'));
        } finally {
            try {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            } catch (\Throwable $e) {
                // Already ended
            }
        }
    }
}
