<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        app(TenantProvisioningService::class)->provision($this->tenant);
    }
}
