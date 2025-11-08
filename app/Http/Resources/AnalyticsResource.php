<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsResource extends JsonResource
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
            'video_id' => $this->video_id,
            'campaign_id' => $this->campaign_id,
            'event_type' => $this->event_type,
            'user_agent' => $this->user_agent,
            'ip_address' => $this->ip_address,
            'referrer' => $this->referrer,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Conditional relationships
            'video' => new VideoResource($this->whenLoaded('video')),
            'campaign' => new CampaignResource($this->whenLoaded('campaign')),
        ];
    }
}