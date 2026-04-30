<?php

namespace App\Http\Requests\Geofence;

use Illuminate\Foundation\Http\FormRequest;

class IngestVehiclePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'timestamp' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
