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
