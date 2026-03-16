<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Add tenant_id processor to all log channels
        $this->registerTenantIdProcessor();
    }

    /**
     * Register tenant_id processor with all log handlers.
     *
     * This ensures every log record includes the current tenant_id.
     */
    private function registerTenantIdProcessor(): void
    {
        $processor = new \App\Logging\TenantIdProcessor();

        // Get all log channels and add the processor
        foreach (\Illuminate\Support\Facades\Log::getChannels() as $channel) {
            if ($channel instanceof \Monolog\Logger) {
                $channel->pushProcessor($processor);
            }
        }
    }
}
