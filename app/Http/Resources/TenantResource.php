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
            'city'       => $this->city ?? null,
            'map_center_lat' => isset($this->map_center_lat) ? (float) $this->map_center_lat : null,
            'map_center_lng' => isset($this->map_center_lng) ? (float) $this->map_center_lng : null,
            'active'     => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
