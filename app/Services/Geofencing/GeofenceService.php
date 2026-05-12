<?php

namespace App\Services\Geofencing;

use App\Models\Geofence;
use App\Models\User;
use App\Support\Geofencing\PolygonNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeofenceService
{
    private ?bool $postgisEnabled = null;

    public function createFromPayload(array $payload, User $actor): Geofence
    {
        $attributes = $this->toPersistedAttributes($payload);
        $attributes['tenant_id'] = (string) $actor->tenant_id;

        $geofence = Geofence::create($attributes);
        $this->syncPostgisGeometry($geofence);

        return $geofence->fresh();
    }

    public function updateFromPayload(Geofence $geofence, array $payload): Geofence
    {
        $attributes = $this->toPersistedAttributes($payload, $geofence);

        $geofence->fill($attributes);
        $geofence->save();

        $this->syncPostgisGeometry($geofence);

        return $geofence->fresh();
    }

    private function toPersistedAttributes(array $payload, ?Geofence $current = null): array
    {
        $type = (string) ($payload['type'] ?? $current?->type);
        if (! in_array($type, ['polygon', 'circle'], true)) {
            throw ValidationException::withMessages([
                'type' => ['type must be polygon or circle.'],
            ]);
        }

        $base = Arr::only($payload, ['name', 'rule_type', 'active', 'schedule', 'hysteresis_m']);
        $base['type'] = $type;

        if ($type === 'polygon') {
            $polygon = $payload['polygon'] ?? Arr::get($current?->geometry_geojson, 'coordinates.0');
            $polygon = PolygonNormalizer::normalize($polygon);
            if ($polygon === null) {
                throw ValidationException::withMessages([
                    'polygon' => ['polygon is required for polygon geofences.'],
                ]);
            }

            $base['geometry_geojson'] = [
                'type' => 'Polygon',
                'coordinates' => [$polygon],
            ];
            $base['center_lat'] = null;
            $base['center_lng'] = null;
            $base['radius_m'] = null;

            $this->assertPostgisPolygonIsValid($polygon);
        }

        if ($type === 'circle') {
            $center = $payload['center'] ?? [
                'lat' => $current?->center_lat,
                'lng' => $current?->center_lng,
            ];

            $radiusM = $payload['radius_m'] ?? $current?->radius_m;
            if (! is_array($center) || ! isset($center['lat'], $center['lng']) || ! $radiusM) {
                throw ValidationException::withMessages([
                    'center' => ['center and radius_m are required for circle geofences.'],
                ]);
            }

            $base['geometry_geojson'] = null;
            $base['center_lat'] = (float) $center['lat'];
            $base['center_lng'] = (float) $center['lng'];
            $base['radius_m'] = (int) $radiusM;
        }

        return $base;
    }

    private function assertPostgisPolygonIsValid(array $polygonRing): void
    {
        if (! $this->canUsePostgis()) {
            return;
        }

        $wkt = $this->polygonRingToWkt($polygonRing);

        try {
            $result = DB::selectOne(
                'SELECT ST_IsValid(ST_GeomFromText(?, 4326)) AS is_valid',
                [$wkt]
            );

            $isValid = (bool) ((int) ($result->is_valid ?? 0));
            if (! $isValid) {
                throw ValidationException::withMessages([
                    'polygon' => ['Polygon is not valid according to PostGIS ST_IsValid.'],
                ]);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function syncPostgisGeometry(Geofence $geofence): void
    {
        if (! $this->canUsePostgis()) {
            return;
        }

        if ($geofence->type !== 'polygon') {
            try {
                DB::statement('UPDATE geofences SET geometry = NULL WHERE id = ?', [$geofence->id]);
            } catch (\Throwable $exception) {
                report($exception);
            }

            return;
        }

        $polygonRing = Arr::get($geofence->geometry_geojson, 'coordinates.0', []);
        if (! is_array($polygonRing) || $polygonRing === []) {
            return;
        }

        $wkt = $this->polygonRingToWkt($polygonRing);

        try {
            DB::statement('UPDATE geofences SET geometry = ST_GeomFromText(?, 4326) WHERE id = ?', [$wkt, $geofence->id]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function polygonRingToWkt(array $ring): string
    {
        $pairs = array_map(
            fn (array $coord) => (float) $coord[0].' '.(float) $coord[1],
            $ring
        );

        return 'POLYGON(('.implode(', ', $pairs).'))';
    }

    private function canUsePostgis(): bool
    {
        if ($this->postgisEnabled !== null) {
            return $this->postgisEnabled;
        }

        if (DB::getDriverName() !== 'pgsql') {
            $this->postgisEnabled = false;

            return false;
        }

        try {
            $row = DB::selectOne("SELECT EXISTS(SELECT 1 FROM pg_extension WHERE extname = 'postgis') AS enabled");
            $this->postgisEnabled = (bool) ($row->enabled ?? false);
        } catch (\Throwable $exception) {
            report($exception);
            $this->postgisEnabled = false;
        }

        return $this->postgisEnabled;
    }
}
