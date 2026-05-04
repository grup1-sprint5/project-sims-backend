<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'slug'    => ['required', 'string', 'max:255', Rule::unique('tenants', 'slug'), 'alpha_dash'],
            'tax_id'  => ['nullable', 'string', 'max:50', 'unique:tenants,tax_id'],
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
