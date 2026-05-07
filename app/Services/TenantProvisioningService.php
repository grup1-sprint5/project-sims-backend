<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class TenantProvisioningService
{
    public const DEFAULT_PASSWORD = 'password';

    /**
     * Complete the operational setup every tenant needs after creation.
     */
    public function provision(Tenant $tenant): array
    {
        $domains = $this->ensureDomains($tenant);
        $this->runMigrations($tenant);
        $users = $this->seedTenantDatabase($tenant);

        return [
            'domains' => $domains,
            'credentials' => $users,
            'password' => self::DEFAULT_PASSWORD,
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

    public function seedTenantDatabase(Tenant $tenant): array
    {
        tenancy()->initialize($tenant);

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            (new PermissionsSeeder())->run();
            (new RolesSeeder())->run();

            $users = [
                'admin' => $this->ensureUser($tenant, 'admin', 'TenantAdmin', 'Admin'),
                'client' => $this->ensureUser($tenant, 'client', 'Client', 'Client'),
                'worker' => $this->ensureUser($tenant, 'worker', 'TenantWorker', 'Worker'),
            ];

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $users;
        } finally {
            tenancy()->end();
        }
    }

    private function ensureUser(Tenant $tenant, string $prefix, string $role, string $label): array
    {
        $email = "{$prefix}@{$tenant->id}.com";

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'tenant_id' => (string) $tenant->id,
                'name' => "{$label} {$tenant->name}",
                'username' => $prefix,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'active' => true,
            ],
        );

        $user->syncRoles([$role]);

        return [
            'email' => $email,
            'role' => $role,
        ];
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
