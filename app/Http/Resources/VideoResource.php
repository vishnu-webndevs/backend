<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
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
            'campaign_id' => $this->campaign_id,
            'name' => $this->title, // Map 'title' to 'name' for frontend compatibility
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'file_path' => $this->file_path,
            'file_url' => $this->file_url,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_url' => $this->thumbnail_url,
            'cta_text' => $this->cta_text,
            'cta_url' => $this->cta_url,
            'weight' => $this->weight,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'duration' => $this->duration,
            'views' => $this->views,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Conditional relationships
            'campaign' => $this->when(
                $this->relationLoaded('campaign'),
                function () {
                    return [
                        'id' => $this->campaign->id,
                        'name' => $this->campaign->name,
                        'slug' => $this->campaign->slug,
                        'is_active' => $this->campaign->is_active,
                    ];
                }
            ),
            'analytics' => AnalyticsResource::collection($this->whenLoaded('analytics')),
            
            // Computed attributes
            'conversion_rate' => $this->when(
                $this->relationLoaded('analytics'),
                function () {
                    $plays = $this->analytics->where('event_type', 'video_play')->count();
                    $clicks = $this->analytics->where('event_type', 'cta_click')->count();
                    return $plays > 0 ? round(($clicks / $plays) * 100, 2) : 0;
                }
            ),
            
            'total_plays' => $this->when(
                $this->relationLoaded('analytics'),
                fn() => $this->analytics->where('event_type', 'video_play')->count()
            ),
            
            'total_cta_clicks' => $this->when(
                $this->relationLoaded('analytics'),
                fn() => $this->analytics->where('event_type', 'cta_click')->count()
            ),
        ];
    }
}