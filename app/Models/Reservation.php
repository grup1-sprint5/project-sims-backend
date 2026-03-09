<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'tenant_id',
        'scheduled_start',
        'scheduled_end',
        'activation_deadline',
        'total_price',
        'cancelled_at',
        'cancellation_fee',
        'status'
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'activation_deadline' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_price' => 'float',
        'cancellation_fee' => 'float',
    ];


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