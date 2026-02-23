<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;
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
        'active'
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }
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
}
