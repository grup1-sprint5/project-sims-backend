<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_plate' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('vehicles', 'license_plate')->ignore($this->route('vehicle')),
            ],
            'brand' => ['sometimes', 'string', 'max:100'],
            'model' => ['sometimes', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Normalizar license_plate a mayúsculas antes de validar
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('license_plate')) {
            $this->merge([
                'license_plate' => strtoupper($this->license_plate),
            ]);
        }
    }
}
