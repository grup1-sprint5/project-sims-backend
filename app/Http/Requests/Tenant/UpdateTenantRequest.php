<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['sometimes', 'string', 'max:255'],
            'slug'    => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('tenants', 'slug')->ignore($this->route('tenant')),
            ],
            'tax_id'  => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('tenants', 'tax_id')->ignore($this->route('tenant')),
            ],
            'email'   => ['nullable', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'active'  => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Normalizar slug a minúsculas antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('slug')) {
            $this->merge([
                'slug' => strtolower($this->slug),
            ]);
        }
    }
}
