<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
