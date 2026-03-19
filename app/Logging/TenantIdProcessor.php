<?php

namespace App\Logging;

use Monolog\LogRecord;

class TenantIdProcessor
{
    /**
     * Add tenant_id to every log record.
     *
     * If tenancy is initialized, include the current tenant ID.
     * Otherwise, use 'central' to indicate central app context.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $tenantId = $this->resolveTenantId();

        // Add to extra fields (context is readonly in Monolog v3+)
        $record->extra['tenant_id'] = $tenantId;

        return $record;
    }

    /**
     * Resolve the current tenant ID from tenancy context.
     */
    private function resolveTenantId(): string
    {
        try {
            if (tenancy()->initialized) {
                return tenancy()->tenant()->id ?? 'unknown';
            }
        } catch (\Throwable $e) {
            // Tenancy not available or not initialized
        }

        return 'central';
    }
}
