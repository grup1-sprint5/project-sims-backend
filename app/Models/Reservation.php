<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToTenant;

class Reservation extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'vehicle_id',
        'scheduled_start',
        'activation_deadline',
        'cancelled_at',
        'cancellation_fee',
        'status'
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'activation_deadline' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancellation_fee' => 'float',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trip()
    {
        return $this->hasOne(Trip::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'active']);
    }
}