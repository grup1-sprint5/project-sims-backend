<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $connection = (string) (config('tenancy.database.central_connection') ?? config('database.default') ?? 'pgsql');
        $slugUniqueColumn = 'id';

        try {
            if (Schema::connection($connection)->hasColumn('tenants', 'slug')) {
                $slugUniqueColumn = 'slug';
            }
        } catch (Throwable $e) {
            report($e);
        }

        return [
            'name'    => ['required', 'string', 'max:255'],
            'slug'    => ['required', 'string', 'max:255', Rule::unique('tenants', $slugUniqueColumn), 'alpha_dash'],
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
