<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceEvent extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'geofence_id',
        'vehicle_id',
        'event_type',
        'position_lat',
        'position_lng',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'position_lat' => 'float',
        'position_lng' => 'float',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
