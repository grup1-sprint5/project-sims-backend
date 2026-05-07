<?php

namespace App\Http\Requests\Geofence;

use App\Rules\ValidPolygonCoordinates;
use App\Support\Geofencing\PolygonNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('polygon')) {
            return;
        }

        $polygon = PolygonNormalizer::normalize($this->input('polygon'));
        if ($polygon !== null) {
            $this->merge(['polygon' => $polygon]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', Rule::in(['polygon', 'circle'])],
            'rule_type' => ['sometimes', Rule::in(['allow', 'forbid'])],
            'active' => ['sometimes', 'boolean'],
            'hysteresis_m' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'schedule' => ['nullable', 'array'],
            'schedule.timezone' => ['nullable', 'string', 'timezone'],
            'schedule.days' => ['nullable', 'array'],
            'schedule.days.*' => ['integer', 'between:1,7'],
            'schedule.start' => ['nullable', 'date_format:H:i'],
            'schedule.end' => ['nullable', 'date_format:H:i'],

            'polygon' => ['sometimes', 'array', new ValidPolygonCoordinates],
            'center' => ['sometimes', 'array'],
            'center.lat' => ['required_with:center', 'numeric', 'between:-90,90'],
            'center.lng' => ['required_with:center', 'numeric', 'between:-180,180'],
            'radius_m' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
