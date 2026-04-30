<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Geofence extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'geometry_geojson',
        'center_lat',
        'center_lng',
        'radius_m',
        'rule_type',
        'active',
        'schedule',
        'hysteresis_m',
    ];

    protected $casts = [
        'geometry_geojson' => 'array',
        'center_lat' => 'float',
        'center_lng' => 'float',
        'radius_m' => 'integer',
        'active' => 'boolean',
        'schedule' => 'array',
        'hysteresis_m' => 'integer',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(GeofenceAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class);
    }

    public function states(): HasMany
    {
        return $this->hasMany(GeofenceVehicleState::class);
    }
}
