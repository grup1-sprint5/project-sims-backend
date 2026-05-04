<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasFactory, SoftDeletes, HasDatabase;

    /**
     * Declare which columns are real DB columns (not stored in the `data` JSON).
     */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'slug', 'name', 'tax_id', 'email', 'phone', 'address', 'active',
            'deleted_at', 'created_at', 'updated_at',
        ];
    }

    protected $fillable = [
        'id',
        'slug',
        'name',
        'tax_id',
        'email',
        'phone',
        'address',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Slug is the external identifier used by auth and X-Tenant routing.
     */
    public function getSlugAttribute(): string
    {
        return (string) ($this->attributes['slug'] ?? $this->attributes['id'] ?? '');
    }

    /**
     * Use slug as tenancy key (schema name), while UUID stays internal.
     */
    public function getTenantKey(): string
    {
        return (string) ($this->attributes['slug'] ?? $this->attributes['id'] ?? '');
    }
}

