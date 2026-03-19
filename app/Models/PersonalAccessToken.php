<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Use the active tenant database context for Sanctum tokens.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
}
