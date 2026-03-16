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
            'slug'       => $this->id,   // backward-compat alias
            'name'       => $this->name,
            'tax_id'     => $this->tax_id,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'address'    => $this->address,
            'active'     => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
