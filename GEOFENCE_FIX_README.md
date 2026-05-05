# Geofence 500 Error - Fix Summary

## Problem Identified
The production server was returning `500 Internal Server Error` for `GET /api/geofences` because the tenant database was missing geofence-related permissions (`geofences.view`, `geofences.manage`, `geofences.delete`).

**Root Cause Log Entry:**
```
There is no permission named `geofences.view` for guard `web`.
at /var/www/html/vendor/spatie/laravel-permission/src/Exceptions/PermissionDoesNotExist.php:11
```

## Solution Deployed

### 1. Permission Safeguard (Already in Code)
The policies `GeofencePolicy` and `GeofenceEventPolicy` have a `hasPermission()` method that catches `PermissionDoesNotExist` exceptions and safely returns `false` instead of crashing.

### 2. Auto-Repair Migration (NEW)
**File:** `database/migrations/tenant/2026_04_30_ensure_geofence_permissions.php`

This migration automatically ensures all geofence permissions and role assignments exist in each tenant database. It will run during deployment and is safe to run multiple times.

### 3. Manual Repair Command (NEW)
**File:** `app/Console/Commands/EnsureGeofencePermissions.php`

For emergency situations, run:
```bash
# For all tenants
php artisan tenants:run geofence:ensure-permissions

# For a specific tenant
php artisan tenants:run --tenants=sims-corp geofence:ensure-permissions

# Or in container
docker compose exec app php artisan tenants:run geofence:ensure-permissions
```

### 4. Regression Test (NEW)
**File:** `tests/Feature/Geofencing/GeofencePermissionFallbackTest.php`

This test ensures endpoints work even when permissions are missing, catching any future regressions.

## Deployment Steps

1. **Merge and Deploy** the feature branch (already done)
2. **Run Database Migrations** on production:
   ```bash
   # This will run the new ensure_geofence_permissions migration on all tenants
   php artisan tenants:migrate
   ```
3. **Verify** by calling `GET /api/geofences` - should return 200 OK with data

## Prevention
Going forward:
- The auto-repair migration ensures permissions are always present
- Tests verify the endpoint tolerates missing permissions gracefully
- New tenants created after this deployment will automatically have correct permissions
