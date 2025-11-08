<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cta_text' => $this->cta_text,
            'cta_url' => $this->cta_url,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_url' => $this->thumbnail_url,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Conditional relationships
            'user' => $this->when(
                $this->relationLoaded('user'),
                function () {
                    return [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'email' => $this->user->email,
                        'role' => $this->user->role,
                    ];
                }
            ),
            'videos' => $this->when(
                $this->relationLoaded('videos'),
                function () {
                    return $this->videos->map(function ($video) {
                        return [
                            'id' => $video->id,
                            'title' => $video->title,
                            'slug' => $video->slug,
                            'status' => $video->status,
                            'views' => $video->views,
                        ];
                    });
                }
            ),
            
            // Computed attributes
            'videos_count' => $this->when(
                $this->relationLoaded('videos'),
                fn() => $this->videos->count()
            ),
            
            'active_videos_count' => $this->when(
                $this->relationLoaded('videos'),
                fn() => $this->videos->where('status', 'active')->count()
            ),
        ];
    }
}