<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToTenant;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'license_plate',
        'brand',
        'model',
        'active',
        'price_per_minute',
        'image_url',
    ];

    protected $casts = [
        'active' => 'boolean',
        'price_per_minute' => 'float',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isAvailable(): bool
    {
        return $this->active && $this->reservations()
            ->whereIn('status', ['pending', 'active'])
            ->doesntExist();
    }
}