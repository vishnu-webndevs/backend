<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Conditional relationships
            'campaigns' => CampaignResource::collection($this->whenLoaded('campaigns')),
            
            // Computed attributes
            'campaigns_count' => $this->when(
                $this->relationLoaded('campaigns'),
                fn() => $this->campaigns->count()
            ),
            
            'active_campaigns_count' => $this->when(
                $this->relationLoaded('campaigns'),
                fn() => $this->campaigns->where('is_active', true)->count()
            ),
        ];
    }
}