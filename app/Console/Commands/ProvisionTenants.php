<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Console\Command;

class ProvisionTenants extends Command
{
    protected $signature = 'tenants:provision {tenant? : Tenant id/slug to provision}';

    protected $description = 'Ensure tenant domains, migrations, roles, permissions, and default credentials exist';

    public function handle(TenantProvisioningService $provisioning): int
    {
        $tenantId = $this->argument('tenant');

        $tenants = $tenantId
            ? Tenant::query()->whereKey($tenantId)->get()
            : Tenant::query()->whereNull('deleted_at')->get();

        if ($tenants->isEmpty()) {
            $this->error($tenantId ? "Tenant {$tenantId} not found." : 'No tenants found.');
            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->line("Provisioning {$tenant->id}...");
            $result = $provisioning->provision($tenant);

            $this->info('  Domains: ' . implode(', ', $result['domains']));
            foreach ($result['credentials'] as $kind => $credentials) {
                $this->line("  {$kind}: {$credentials['email']} / {$result['password']}");
            }
        }

        return self::SUCCESS;
    }
}
