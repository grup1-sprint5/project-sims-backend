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
     * The tenant `id` IS the slug (human-readable, unique identifier).
     * stancl/tenancy uses string primary keys by default – no changes needed.
     * The schema name will be: "tenant_" + id (configured via tenancy.database.prefix).
     */

    /**
     * Declare which columns are real DB columns (not stored in the `data` JSON).
     */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'name', 'tax_id', 'email', 'phone', 'address', 'active',
            'deleted_at', 'created_at', 'updated_at',
        ];
    }

    protected $fillable = [
        'id',      // The slug – must be provided explicitly on create
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
        return $this->id;
    }
}

