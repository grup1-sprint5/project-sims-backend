<?php

namespace App\Rules;

use App\Support\Geofencing\PolygonNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPolygonCoordinates implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ring = PolygonNormalizer::normalize($value);
        if ($ring === null) {
            $fail('Polygon must include at least 3 valid coordinates.');

            return;
        }

        foreach ($ring as $coordinate) {
            if (! is_array($coordinate) || count($coordinate) !== 2) {
                $fail('Each polygon coordinate must be [lng, lat].');

                return;
            }

            $lng = $coordinate[0] ?? null;
            $lat = $coordinate[1] ?? null;

            if (! is_numeric($lng) || ! is_numeric($lat)) {
                $fail('Polygon coordinates must be numeric.');

                return;
            }

            if ((float) $lat < -90 || (float) $lat > 90 || (float) $lng < -180 || (float) $lng > 180) {
                $fail('Polygon coordinates are out of range.');

                return;
            }
        }

        if ($this->hasSelfIntersections($ring)) {
            $fail('Polygon cannot self-intersect.');
        }
    }

    private function hasSelfIntersections(array $ring): bool
    {
        $segments = [];
        for ($index = 0; $index < count($ring) - 1; $index++) {
            $segments[] = [$ring[$index], $ring[$index + 1]];
        }

        $count = count($segments);
        for ($left = 0; $left < $count; $left++) {
            for ($right = $left + 1; $right < $count; $right++) {
                if (abs($left - $right) <= 1) {
                    continue;
                }

                if ($left === 0 && $right === $count - 1) {
                    continue;
                }

                if ($this->segmentsIntersect($segments[$left], $segments[$right])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function segmentsIntersect(array $segmentA, array $segmentB): bool
    {
        [$a1, $a2] = $segmentA;
        [$b1, $b2] = $segmentB;

        $o1 = $this->orientation($a1, $a2, $b1);
        $o2 = $this->orientation($a1, $a2, $b2);
        $o3 = $this->orientation($b1, $b2, $a1);
        $o4 = $this->orientation($b1, $b2, $a2);

        if ($o1 !== $o2 && $o3 !== $o4) {
            return true;
        }

        return false;
    }

    private function orientation(array $pointA, array $pointB, array $pointC): int
    {
        $value = (($pointB[1] - $pointA[1]) * ($pointC[0] - $pointB[0]))
            - (($pointB[0] - $pointA[0]) * ($pointC[1] - $pointB[1]));

        if (abs($value) < 1e-12) {
            return 0;
        }

        return $value > 0 ? 1 : 2;
    }
}
