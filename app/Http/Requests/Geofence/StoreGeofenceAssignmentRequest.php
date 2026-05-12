<?php

namespace App\Http\Requests\Geofence;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGeofenceAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assign_type' => ['required', Rule::in(['vehicle', 'fleet'])],
            'assign_id' => ['required', 'string', 'max:120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('assign_type') !== 'vehicle') {
                return;
            }

            $vehicleId = $this->input('assign_id');
            if (!is_numeric($vehicleId)) {
                $validator->errors()->add('assign_id', 'assign_id must be a valid vehicle id when assign_type is vehicle.');
                return;
            }

            $exists = Vehicle::query()->whereKey((int) $vehicleId)->exists();
            if (!$exists) {
                $validator->errors()->add('assign_id', 'Vehicle not found in current tenant.');
            }
        });
    }
}
