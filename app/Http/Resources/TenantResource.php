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
        $domains = $this->whenLoaded('domains', fn() => $this->domains->pluck('domain')->values());
        $primaryDomain = $this->whenLoaded('domains', fn() => $this->domains->first()?->domain ?? null);

        return [
            'id'             => $this->id,
            'slug'           => $this->id,
            'name'           => $this->name,
            'tax_id'         => $this->tax_id,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'address'        => $this->address,
            'city'           => $this->city ?? null,
            'map_center_lat' => isset($this->map_center_lat) ? (float) $this->map_center_lat : null,
            'map_center_lng' => isset($this->map_center_lng) ? (float) $this->map_center_lng : null,
            'active'         => $this->active,
            'domains'        => $domains,
            'primary_domain' => $primaryDomain,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
