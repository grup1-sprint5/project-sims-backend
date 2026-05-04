<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantsMigrateIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate-ids {--dry-run : Show what would change without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replace slug-based tenant_id values with UUIDs and upgrade tenant IDs to UUIDs';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $hasSlugColumn = Schema::hasColumn('tenants', 'slug');
        $tenants = DB::table('tenants')
            ->select(['id', $hasSlugColumn ? 'slug' : DB::raw('id as slug')])
            ->get();
        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return self::SUCCESS;
        }

        $this->info($dryRun ? 'Dry run enabled.' : 'Migrating tenant_id values to UUIDs.');

        foreach ($tenants as $tenant) {
            $slug = trim((string) ($tenant->slug ?? $tenant->id ?? ''));
            $currentId = (string) ($tenant->id ?? '');

            if ($slug === '' || $currentId === '') {
                $this->warn('Skipping tenant with missing slug or id.');
                continue;
            }

            $needsIdUpdate = !Str::isUuid($currentId);
            $uuid = $needsIdUpdate ? (string) Str::uuid() : $currentId;

            if (!$dryRun && $needsIdUpdate) {
                DB::table('tenants')
                    ->where('id', $currentId)
                    ->update($hasSlugColumn ? ['id' => $uuid, 'slug' => $slug] : ['id' => $uuid]);
            } elseif (!$dryRun && $hasSlugColumn) {
                DB::table('tenants')
                    ->where('id', $currentId)
                    ->update(['slug' => $slug]);
            }

            $sqlUpdated = 0;
            $domainUpdated = 0;
            if (Schema::hasTable('login_exchange_tokens')) {
                if ($dryRun) {
                    $sqlUpdated = DB::table('login_exchange_tokens')->where('tenant_id', $slug)->count();
                } else {
                    $sqlUpdated = DB::table('login_exchange_tokens')
                        ->where('tenant_id', $slug)
                        ->update(['tenant_id' => $uuid]);
                }
            }

            if (Schema::hasTable('domains')) {
                if ($dryRun) {
                    $domainUpdated = DB::table('domains')->where('tenant_id', $slug)->count();
                } else {
                    $domainUpdated = DB::table('domains')
                        ->where('tenant_id', $slug)
                        ->update(['tenant_id' => $uuid]);
                }
            }

            $mongoUpdated = null;
            if ($this->canUseMongo()) {
                try {
                    $query = DB::connection('mongodb')->table('vehicle_locations')->where('tenant_id', $slug);
                    $mongoUpdated = $dryRun ? $query->count() : $query->update(['tenant_id' => $uuid]);
                } catch (\Throwable $e) {
                    $this->warn("Mongo update failed for {$slug}: {$e->getMessage()}");
                }
            }

            $mongoMsg = $mongoUpdated === null ? 'mongo: skipped' : "mongo: {$mongoUpdated}";
            $idMsg = $needsIdUpdate ? "tenant id: {$currentId} -> {$uuid}" : 'tenant id: ok';
            $this->line("{$slug} | {$idMsg} | login_exchange_tokens: {$sqlUpdated} | domains: {$domainUpdated} | {$mongoMsg}");
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function canUseMongo(): bool
    {
        if (!config('database.connections.mongodb.dsn') && !env('MONGODB_URI')) {
            return false;
        }

        try {
            DB::connection('mongodb')->getMongoClient();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
