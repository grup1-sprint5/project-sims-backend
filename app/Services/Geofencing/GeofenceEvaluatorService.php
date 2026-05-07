<?php

namespace App\Services\Geofencing;

use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Models\GeofenceVehicleState;
use App\Models\Vehicle;
use App\Support\Geofencing\PolygonNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeofenceEvaluatorService
{
    private ?bool $postgisEnabled = null;

    public function processVehiclePosition(
        Vehicle $vehicle,
        float $lat,
        float $lng,
        CarbonImmutable $occurredAt,
        array $metadata = []
    ): array {
        $geofences = $this->applicableGeofencesForVehicle($vehicle)
            ->filter(fn (Geofence $geofence) => $this->isScheduleActive($geofence, $occurredAt));

        $createdEvents = [];

        foreach ($geofences as $geofence) {
            $state = GeofenceVehicleState::query()->firstOrNew([
                'tenant_id' => (string) $vehicle->tenant_id,
                'geofence_id' => $geofence->id,
                'vehicle_id' => $vehicle->id,
            ]);

            $wasInside = $state->exists ? (bool) $state->inside : null;
            $isInside = $this->isInsideGeofenceWithHysteresis($geofence, $lat, $lng, (bool) $state->inside);

            $state->inside = $isInside;
            $state->last_seen_at = $occurredAt;
            $state->save();

            if ($wasInside === null || $wasInside === $isInside) {
                continue;
            }

            if ($isInside) {
                $createdEvents[] = $this->createEvent($geofence, $vehicle, 'enter', $lat, $lng, $occurredAt, $metadata);

                if ($geofence->rule_type === 'forbid') {
                    $createdEvents[] = $this->createEvent($geofence, $vehicle, 'violation', $lat, $lng, $occurredAt, $metadata);
                }
            } else {
                $createdEvents[] = $this->createEvent($geofence, $vehicle, 'exit', $lat, $lng, $occurredAt, $metadata);
            }
        }

        return $createdEvents;
    }

    private function applicableGeofencesForVehicle(Vehicle $vehicle): Collection
    {
        $fleetKeys = $this->fleetKeys($vehicle);

        return Geofence::query()
            ->where('tenant_id', (string) $vehicle->tenant_id)
            ->where('active', true)
            ->whereHas('assignments', function ($query) use ($vehicle, $fleetKeys): void {
                $query->where(function ($inner) use ($vehicle): void {
                    $inner->where('assign_type', 'vehicle')
                        ->where('assign_id', (string) $vehicle->id);
                });

                if ($fleetKeys !== []) {
                    $query->orWhere(function ($inner) use ($fleetKeys): void {
                        $inner->where('assign_type', 'fleet')
                            ->whereIn('assign_id', $fleetKeys);
                    });
                }
            })
            ->get();
    }

    private function fleetKeys(Vehicle $vehicle): array
    {
        $keys = [];

        if (! empty($vehicle->fleet_id)) {
            $keys[] = (string) $vehicle->fleet_id;
        }

        if (! empty($vehicle->type)) {
            $keys[] = (string) $vehicle->type;
        }

        return array_values(array_unique($keys));
    }

    private function isScheduleActive(Geofence $geofence, CarbonImmutable $now): bool
    {
        $schedule = $geofence->schedule;
        if (! is_array($schedule) || $schedule === []) {
            return true;
        }

        $timezone = $schedule['timezone'] ?? 'UTC';
        $inTz = $now->setTimezone($timezone);

        $days = $schedule['days'] ?? null;
        if (is_array($days) && $days !== []) {
            $isoDay = (int) $inTz->dayOfWeekIso;
            if (! in_array($isoDay, array_map('intval', $days), true)) {
                return false;
            }
        }

        $start = $schedule['start'] ?? null;
        $end = $schedule['end'] ?? null;

        if (! $start || ! $end) {
            return true;
        }

        $currentMinutes = ((int) $inTz->format('H')) * 60 + ((int) $inTz->format('i'));
        $startMinutes = $this->hourStringToMinutes((string) $start);
        $endMinutes = $this->hourStringToMinutes((string) $end);

        if ($startMinutes <= $endMinutes) {
            return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
        }

        return $currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes;
    }

    private function hourStringToMinutes(string $hour): int
    {
        [$h, $m] = explode(':', $hour);

        return ((int) $h) * 60 + ((int) $m);
    }

    private function isInsideGeofenceWithHysteresis(Geofence $geofence, float $lat, float $lng, bool $wasInside): bool
    {
        $hysteresis = (float) ($geofence->hysteresis_m ?? 0);

        if ($geofence->type === 'circle') {
            $distance = $this->haversineMeters($lat, $lng, (float) $geofence->center_lat, (float) $geofence->center_lng);
            $radius = (float) $geofence->radius_m;

            if ($wasInside) {
                return $distance <= ($radius + $hysteresis);
            }

            return $distance <= max($radius - $hysteresis, 0);
        }

        $ring = Arr::get($geofence->geometry_geojson, 'coordinates.0', []);
        $ring = PolygonNormalizer::normalize($ring);
        if ($ring === null) {
            return false;
        }

        $inside = $this->pointInPolygon($lng, $lat, $ring);

        if ($inside || ! $wasInside || $hysteresis <= 0) {
            return $inside;
        }

        $distanceToEdge = $this->distanceToPolygonEdgeMeters($lng, $lat, $ring);

        return $distanceToEdge <= $hysteresis;
    }

    private function pointInPolygon(float $pointLng, float $pointLat, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        if ($count < 4) {
            return false;
        }

        for ($left = 0, $right = $count - 1; $left < $count; $right = $left++) {
            $lng1 = (float) ($ring[$left][0] ?? 0);
            $lat1 = (float) ($ring[$left][1] ?? 0);
            $lng2 = (float) ($ring[$right][0] ?? 0);
            $lat2 = (float) ($ring[$right][1] ?? 0);

            $intersects = (($lat1 > $pointLat) !== ($lat2 > $pointLat))
                && ($pointLng < (($lng2 - $lng1) * ($pointLat - $lat1)) / (($lat2 - $lat1) ?: 1e-12) + $lng1);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function distanceToPolygonEdgeMeters(float $pointLng, float $pointLat, array $ring): float
    {
        $min = INF;

        for ($index = 0; $index < count($ring) - 1; $index++) {
            $a = $ring[$index];
            $b = $ring[$index + 1];
            $distance = $this->distancePointToSegmentMeters(
                $pointLng,
                $pointLat,
                (float) ($a[0] ?? 0),
                (float) ($a[1] ?? 0),
                (float) ($b[0] ?? 0),
                (float) ($b[1] ?? 0)
            );

            $min = min($min, $distance);
        }

        return $min === INF ? PHP_FLOAT_MAX : $min;
    }

    private function distancePointToSegmentMeters(
        float $pointLng,
        float $pointLat,
        float $lng1,
        float $lat1,
        float $lng2,
        float $lat2
    ): float {
        $meanLatRad = deg2rad(($lat1 + $lat2 + $pointLat) / 3);
        $kx = 111320 * cos($meanLatRad);
        $ky = 110540;

        $px = $pointLng * $kx;
        $py = $pointLat * $ky;
        $x1 = $lng1 * $kx;
        $y1 = $lat1 * $ky;
        $x2 = $lng2 * $kx;
        $y2 = $lat2 * $ky;

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;

        if ($dx == 0.0 && $dy == 0.0) {
            return sqrt(($px - $x1) ** 2 + ($py - $y1) ** 2);
        }

        $t = (($px - $x1) * $dx + ($py - $y1) * $dy) / (($dx ** 2) + ($dy ** 2));
        $t = max(0.0, min(1.0, $t));

        $cx = $x1 + $t * $dx;
        $cy = $y1 + $t * $dy;

        return sqrt(($px - $cx) ** 2 + ($py - $cy) ** 2);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function createEvent(
        Geofence $geofence,
        Vehicle $vehicle,
        string $eventType,
        float $lat,
        float $lng,
        CarbonImmutable $occurredAt,
        array $metadata
    ): GeofenceEvent {
        $event = GeofenceEvent::query()->create([
            'tenant_id' => (string) $vehicle->tenant_id,
            'geofence_id' => $geofence->id,
            'vehicle_id' => $vehicle->id,
            'event_type' => $eventType,
            'position_lat' => $lat,
            'position_lng' => $lng,
            'occurred_at' => $occurredAt,
            'metadata' => $metadata,
        ]);

        if ($this->canUsePostgis()) {
            try {
                DB::statement(
                    'UPDATE geofence_events SET position = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$lng, $lat, $event->id]
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $event;
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
