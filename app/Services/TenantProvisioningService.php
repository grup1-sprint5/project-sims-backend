<?php

namespace App\Services;

use App\Models\Tenant;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

class TenantProvisioningService
{
    /**
     * Complete the operational setup every tenant needs after creation.
     */
    public function provision(Tenant $tenant): array
    {
        $domains = $this->ensureDomains($tenant);
        $this->runMigrations($tenant);
        $this->seedTenantDatabase($tenant);

        return [
            'domains' => $domains,
            'credentials' => [],
            'password' => null,
        ];
    }

    /**
     * Register tenant subdomains for every configured production central domain.
     */
    public function ensureDomains(Tenant $tenant): array
    {
        $domains = [];

        foreach ($this->baseDomains() as $baseDomain) {
            $domain = "{$tenant->id}.{$baseDomain}";

            $tenant->domains()->firstOrCreate([
                'domain' => $domain,
            ]);

            $domains[] = $domain;
        }

        return $domains;
    }

    public function runMigrations(Tenant $tenant): void
    {
        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
            '--force' => true,
        ]);
    }

    public function seedTenantDatabase(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            (new PermissionsSeeder())->run();
            (new RolesSeeder())->run();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Production base domains where tenant subdomains should work.
     */
    private function baseDomains(): array
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $domains = collect($appHost ? [$appHost] : [])
            ->merge(config('tenancy.central_domains', []));

        return $domains
            ->map(fn ($domain) => strtolower(trim((string) $domain)))
            ->filter(fn ($domain) => $domain !== '' && str_contains($domain, '.'))
            ->reject(fn ($domain) => in_array($domain, ['127.0.0.1', 'localhost'], true))
            ->reject(fn ($domain) => str_starts_with($domain, 'www.'))
            ->unique()
            ->values()
            ->all();
    }
}
