<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\RolePermissionsSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SeedTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The tenant to seed.
     */
    public Tenant $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->queue = 'default';
        $this->delay = 0;
    }

    /**
     * Execute the job.
     *
     * Seeds permissions, roles, and creates initial admin user for the tenant.
     */
    public function handle(): void
    {
        tenancy()->initialize($this->tenant);

        try {
            // Run permission and role seeders
            $this->runSeeders();

            // Create initial admin user
            $this->createAdminUser();
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Run seeders for permissions and roles.
     */
    private function runSeeders(): void
    {
        $seederClass = new class {
            public function __invoke()
            {
                (new PermissionsSeeder())->run();
                (new RolesSeeder())->run();
                (new RolePermissionsSeeder())->run();
            }
        };

        $seederClass();
    }

    /**
     * Create initial admin user for the tenant.
     */
    private function createAdminUser(): void
    {
        $tenant = $this->tenant;

        // Generate admin credentials from tenant info
        $adminEmail = $this->getAdminEmail($tenant);
        $adminUsername = $this->getAdminUsername($tenant);

        // Check if admin already exists (avoid duplication)
        if (User::where('email', $adminEmail)->exists()) {
            return;
        }

        // Create admin user
        $admin = User::create([
            'name' => ucwords($tenant->name) . ' Administrator',
            'username' => $adminUsername,
            'email' => $adminEmail,
            'password' => Hash::make('change-me-' . $this->tenant->id),
            'active' => true,
            'tenant_id' => $tenant->id,
        ]);

        // Assign TenantAdmin role
        $admin->assignRole('TenantAdmin');
    }

    /**
     * Generate admin email from tenant info.
     *
     * If tenant has email, use admin@{tenant.email domain}.
     * Otherwise, use admin-{tenant.id}@localhost.
     */
    private function getAdminEmail(Tenant $tenant): string
    {
        if ($tenant->email && str_contains($tenant->email, '@')) {
            $domain = substr($tenant->email, strpos($tenant->email, '@') + 1);
            return 'admin@' . $domain;
        }

        return 'admin-' . $tenant->id . '@localhost';
    }

    /**
     * Generate admin username from tenant slug/id.
     *
     * Format: admin-{tenant-id-slug}
     */
    private function getAdminUsername(Tenant $tenant): string
    {
        return 'admin-' . str_replace('_', '-', $tenant->id);
    }
}
