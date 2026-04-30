<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceAssignment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'geofence_id',
        'assign_type',
        'assign_id',
    ];

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }
}
