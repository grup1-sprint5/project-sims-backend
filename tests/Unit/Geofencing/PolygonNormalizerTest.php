<?php

namespace Tests\Unit\Geofencing;

use App\Support\Geofencing\PolygonNormalizer;
use PHPUnit\Framework\TestCase;

class PolygonNormalizerTest extends TestCase
{
    public function test_it_closes_map_coordinate_polygons(): void
    {
        $polygon = PolygonNormalizer::normalize([
            ['lat' => 41.3800, 'lng' => 2.1700],
            ['lat' => 41.3800, 'lng' => 2.1800],
            ['lat' => 41.3900, 'lng' => 2.1800],
            ['lat' => 41.3900, 'lng' => 2.1700],
        ]);

        $this->assertSame([
            [2.17, 41.38],
            [2.18, 41.38],
            [2.18, 41.39],
            [2.17, 41.39],
            [2.17, 41.38],
        ], $polygon);
    }

    public function test_it_accepts_geojson_polygons(): void
    {
        $polygon = PolygonNormalizer::normalize([
            'type' => 'Polygon',
            'coordinates' => [[
                [2.1700, 41.3800],
                [2.1800, 41.3800],
                [2.1800, 41.3900],
                [2.1700, 41.3900],
            ]],
        ]);

        $this->assertSame([2.17, 41.38], $polygon[0]);
        $this->assertSame([2.17, 41.38], $polygon[4]);
    }
}
