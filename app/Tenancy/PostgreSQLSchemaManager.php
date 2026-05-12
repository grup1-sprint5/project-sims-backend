<?php

declare(strict_types=1);

namespace App\Tenancy;

use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager as BaseManager;

/**
 * Custom PostgreSQL schema manager that includes the `public` schema in the
 * search_path so tenant contexts can still reach the central `tenants` table
 * (needed for TenantPolicy and TenantController queries).
 */
class PostgreSQLSchemaManager extends BaseManager
{
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $quotedSchema = '"' . str_replace('"', '""', $databaseName) . '"';
        $baseConfig['search_path'] = $quotedSchema . ',public';

        return $baseConfig;
    }
}
