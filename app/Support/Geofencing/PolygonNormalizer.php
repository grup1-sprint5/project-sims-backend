<?php

namespace App\Support\Geofencing;

class PolygonNormalizer
{
    public static function normalize(mixed $value): ?array
    {
        $ring = self::extractRing($value);
        if (! is_array($ring)) {
            return null;
        }

        $coordinates = [];
        foreach ($ring as $coordinate) {
            $normalized = self::normalizeCoordinate($coordinate);
            if ($normalized === null) {
                return null;
            }

            $coordinates[] = $normalized;
        }

        if (count($coordinates) < 3) {
            return null;
        }

        if (! self::sameCoordinate($coordinates[0], $coordinates[count($coordinates) - 1])) {
            $coordinates[] = $coordinates[0];
        }

        if (count($coordinates) < 4 || self::uniqueVertexCount($coordinates) < 3) {
            return null;
        }

        return $coordinates;
    }

    private static function extractRing(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        if (($value['type'] ?? null) === 'Feature') {
            return self::extractRing($value['geometry'] ?? null);
        }

        if (($value['type'] ?? null) === 'Polygon') {
            return self::extractRing($value['coordinates'] ?? null);
        }

        if (array_key_exists('coordinates', $value) && is_array($value['coordinates'])) {
            return self::extractRing($value['coordinates']);
        }

        if (isset($value[0]) && is_array($value[0]) && isset($value[0][0]) && is_array($value[0][0])) {
            return $value[0];
        }

        return $value;
    }

    private static function normalizeCoordinate(mixed $coordinate): ?array
    {
        if (! is_array($coordinate)) {
            return null;
        }

        if (isset($coordinate['lng']) || isset($coordinate['lon']) || isset($coordinate['longitude'])) {
            $lng = $coordinate['lng'] ?? $coordinate['lon'] ?? $coordinate['longitude'] ?? null;
            $lat = $coordinate['lat'] ?? $coordinate['latitude'] ?? null;
        } else {
            $lng = $coordinate[0] ?? null;
            $lat = $coordinate[1] ?? null;
        }

        if (! is_numeric($lng) || ! is_numeric($lat)) {
            return null;
        }

        $lng = (float) $lng;
        $lat = (float) $lat;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [$lng, $lat];
    }

    private static function sameCoordinate(array $left, array $right): bool
    {
        return abs((float) $left[0] - (float) $right[0]) < 1e-12
            && abs((float) $left[1] - (float) $right[1]) < 1e-12;
    }

    private static function uniqueVertexCount(array $coordinates): int
    {
        $unique = [];
        foreach ($coordinates as $index => $coordinate) {
            if ($index === count($coordinates) - 1 && self::sameCoordinate($coordinate, $coordinates[0])) {
                continue;
            }

            $unique[number_format((float) $coordinate[0], 12, '.', '').':'.number_format((float) $coordinate[1], 12, '.', '')] = true;
        }

        return count($unique);
    }
}
