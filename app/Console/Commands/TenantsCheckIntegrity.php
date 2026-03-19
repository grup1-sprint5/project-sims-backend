<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantsCheckIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:check-integrity {--repair : Attempt to repair missing schemas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check integrity of tenant schemas and report issues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $repair = $this->option('repair');
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->info('No tenants to check.');
            return self::SUCCESS;
        }

        $this->info('Checking integrity of ' . count($tenants) . ' tenant(s)...' . PHP_EOL);

        $issues = 0;
        $fixed = 0;

        foreach ($tenants as $tenant) {
            $schemaName = config('tenancy.database.prefix') . $tenant->id;
            $exists = $this->schemaExists($schemaName);

            if (!$exists) {
                $this->error("✗ Tenant '{$tenant->id}' - Schema '{$schemaName}' does not exist");
                $issues++;

                if ($repair) {
                    try {
                        tenancy()->initialize($tenant);
                        tenancy()->end();
                        $this->info("  → Schema created and migrated");
                        $fixed++;
                    } catch (\Throwable $e) {
                        $this->error("  → Failed to repair: " . $e->getMessage());
                    }
                }
            } else {
                $tableCount = $this->countTablesInSchema($schemaName);
                $this->line("✓ Tenant '{$tenant->id}' - Schema exists with {$tableCount} tables");
            }
        }

        $this->info(PHP_EOL . "=== Summary ===");
        $this->info("Total tenants: " . count($tenants));
        $this->error("Issues found: " . $issues);
        if ($repair) {
            $this->info("Issues fixed: " . $fixed);
        }

        if ($issues > 0 && !$repair) {
            $this->info("Run with --repair flag to attempt automatic fixes.");
        }

        return $issues === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Check if a schema exists in PostgreSQL.
     */
    private function schemaExists(string $schemaName): bool
    {
        try {
            $result = DB::selectOne("
                SELECT schema_name 
                FROM information_schema.schemata 
                WHERE schema_name = ?
            ", [$schemaName]);

            return $result !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Count tables in a schema.
     */
    private function countTablesInSchema(string $schemaName): int
    {
        try {
            $result = DB::selectOne("
                SELECT COUNT(*) as table_count
                FROM information_schema.tables
                WHERE table_schema = ?
                AND table_type = 'BASE TABLE'
            ", [$schemaName]);

            return $result->table_count ?? 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
