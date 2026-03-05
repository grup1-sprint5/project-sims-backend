<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SensorDataService
{
    /**
     * Obté les últimes lectures de sensors des de MongoDB.
     *
     * @param  int         $limit       Nombre màxim de resultats (per defecte 50)
     * @param  string|null $deviceId    Filtra per device_id (opcional)
     * @param  string|null $sensorType  Filtra per sensor_type (opcional)
     * @return array
     */
    public function getReadings(int $limit = 50, ?string $deviceId = null, ?string $sensorType = null): array
    {
        $query = DB::connection('mongodb')->table('sensor_readings');

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        }

        if ($sensorType) {
            $query->where('sensor_type', $sensorType);
        }

        $readings = $query->orderBy('created_at', 'desc')
                          ->limit($limit)
                          ->get();

        return $readings->map(function ($reading) {
            $arr = (array) $reading;
            // Convertim l'ObjectId de MongoDB a string llegible
            if (isset($arr['_id'])) {
                $arr['id'] = (string) $arr['_id'];
                unset($arr['_id']);
            }
            // Normalitzem el camp created_at si és un objecte UTCDateTime
            if (isset($arr['created_at']) && is_object($arr['created_at'])) {
                $arr['created_at'] = (string) $arr['created_at'];
            }
            return $arr;
        })->toArray();
    }

    /**
     * Obté els device_ids únics que han enviat lectures.
     *
     * @return array
     */
    public function getDeviceIds(): array
    {
        $ids = DB::connection('mongodb')
            ->table('sensor_readings')
            ->distinct('device_id')
            ->get();

        return $ids->map(fn($item) => $item->device_id ?? (string) $item)->filter()->values()->toArray();
    }

    /**
     * Obté l'última lectura d'un dispositiu concret.
     *
     * @param  string $deviceId
     * @return array|null
     */
    public function getLatestByDevice(string $deviceId): ?array
    {
        $doc = DB::connection('mongodb')
            ->table('sensor_readings')
            ->where('device_id', $deviceId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$doc) {
            return null;
        }

        $arr = (array) $doc;
        if (isset($arr['_id'])) {
            $arr['id'] = (string) $arr['_id'];
            unset($arr['_id']);
        }
        if (isset($arr['created_at']) && is_object($arr['created_at'])) {
            $arr['created_at'] = (string) $arr['created_at'];
        }

        return $arr;
    }
}
