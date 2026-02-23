<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => ['sometimes', 'exists:users,id'],
            'vehicle_id' => ['sometimes', 'nullable', 'exists:vehicles,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
