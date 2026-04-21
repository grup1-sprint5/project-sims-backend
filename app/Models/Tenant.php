<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasFactory, SoftDeletes, HasDatabase, HasDomains;

    /**
     * Legacy compatibility note:
     * - Newer schema may use string id as tenant key.
     * - Legacy schema can use numeric id + string slug as tenant key.
     */

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
     * Backward-compat accessor: $tenant->slug returns $tenant->id.
     */
    public function getSlugAttribute(): string
    {
        return (string) ($this->attributes['slug'] ?? $this->id);
    }

    /**
     * Compatibility for legacy central schemas where id is numeric and slug stores tenant key.
     */
    public function getTenantKey(): string
    {
        if (!empty($this->attributes['slug'])) {
            return (string) $this->attributes['slug'];
        }

        return (string) parent::getTenantKey();
    }
}

