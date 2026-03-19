<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyIdentificationErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_clean_error_when_tenant_header_is_missing(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'someone@example.com',
            'password' => 'secret',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Tenant not identified. Provide a valid X-Tenant header.',
                'error' => 'tenant_not_identified',
            ])
            ->assertJsonMissingPath('trace');
    }

    public function test_api_returns_clean_error_when_tenant_header_is_invalid(): void
    {
        $response = $this
            ->withHeader('X-Tenant', 'tenant-that-does-not-exist')
            ->postJson('/api/login', [
                'email' => 'someone@example.com',
                'password' => 'secret',
            ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Tenant not identified. Provide a valid X-Tenant header.',
                'error' => 'tenant_not_identified',
            ])
            ->assertJsonMissingPath('trace');
    }
}
