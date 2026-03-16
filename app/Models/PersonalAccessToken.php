<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Store API tokens in the central connection to avoid tenant-scope mismatches
 * during Sanctum token lookup.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'pgsql';
}
