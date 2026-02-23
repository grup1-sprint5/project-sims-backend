<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'tax_id'     => $this->tax_id,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'address'    => $this->address,
            'active'     => $this->active,
            'users_count'    => $this->whenCounted('users'),
            'vehicles_count' => $this->whenCounted('vehicles'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
