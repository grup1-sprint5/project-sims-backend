<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Auth\Access\Gate;

// Models
use App\Models\User;
use App\Models\Ticket;
use App\Models\Vehicle;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\Geofence;
use App\Models\GeofenceEvent;
use Spatie\Permission\Models\Role;

// Policies
use App\Policies\UserPolicy;
use App\Policies\TicketPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\ReservationPolicy;
use App\Policies\RolePolicy;
use App\Policies\TenantPolicy;
use App\Policies\GeofencePolicy;
use App\Policies\GeofenceEventPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Ticket::class => TicketPolicy::class,
        Vehicle::class => VehiclePolicy::class,
        Reservation::class => ReservationPolicy::class,
        Role::class => RolePolicy::class,
        Tenant::class => TenantPolicy::class,
        Geofence::class => GeofencePolicy::class,
        GeofenceEvent::class => GeofenceEventPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Implicitly grant all permissions to SuperAdmins
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });
    }

    /**
     * Register the application's policies.
     */
    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            \Illuminate\Support\Facades\Gate::policy($model, $policy);
        }
    }
}
