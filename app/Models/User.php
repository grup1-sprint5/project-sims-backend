<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use App\Models\Traits\BelongsToTenant;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, BelongsToTenant;
    use HasRoles;

    public $guard_name = 'web';

    /**
     * Assign a default role when a user is created if they have no role.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            if ($user->roles->isEmpty()) {
                $role = Role::firstOrCreate(['name' => 'Client']);
                $user->assignRole($role);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'active',
        'wallet_balance',
        'tenant_id',  // string slug, auto-assigned by BelongsToTenant
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Relationship: user belongs to a role.
     */
    /* USE THIS WHEN ROLES ARE IMPLEMENTED
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    */
    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'wallet_balance' => 'decimal:2',
    ];
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function trips()
    {
        return $this->hasManyThrough(Trip::class, Reservation::class);
    }
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Tenant this user belongs to.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if the user is the central SuperAdmin (not a tenant-scoped admin).
     * Returns false when tenancy is active to prevent tenant SuperAdmin users
     * from accessing cross-tenant data.
     */
    public function isSuperAdmin(): bool
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return false;
        }
        return $this->hasRole('SuperAdmin');
    }

    /**
     * Check if the user has the TenantAdmin role.
     */
    public function isTenantAdmin(): bool
    {
        return $this->hasRole('TenantAdmin');
    }
}
