<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_plate' => ['required', 'string', 'max:20', 'unique:vehicles,license_plate'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    /**
     * Normalizar license_plate a mayúsculas antes de validar
     */
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('license_plate')) {
            $payload['license_plate'] = strtoupper($this->license_plate);
        }

        if (!$this->has('latitude') && $this->has('lat')) {
            $payload['latitude'] = $this->input('lat');
        }

        if (!$this->has('longitude')) {
            if ($this->has('lng')) {
                $payload['longitude'] = $this->input('lng');
            } elseif ($this->has('lon')) {
                $payload['longitude'] = $this->input('lon');
            }
        }

        if (!empty($payload)) {
            $this->merge($payload);
        }
    }
}
