<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantRequest extends Model
{
    use HasFactory;

    protected $table = 'tenant_requests';

    protected $fillable = [
        'name',
        'slug',
        'email',
        'domain',
        'status',
        'requested_at',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];
}
