<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Campaign extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'cta_text',
        'cta_url',
        'thumbnail_path',
        'is_active',
        'settings'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array'
    ];

    protected $appends = [
        'thumbnail_url'
    ];

    /**
     * Get the user that owns the campaign
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the videos for the campaign
     */
    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    /**
     * Get active videos for the campaign
     */
    public function activeVideos(): HasMany
    {
        return $this->hasMany(Video::class)->where('is_active', true);
    }

    /**
     * Get analytics for the campaign
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(Analytics::class);
    }

    /**
     * Generate a unique slug for the campaign
     */
    public static function generateSlug(string $name): string
    {
        Log::info('Campaign::generateSlug called', ['name' => $name]);
        
        $slug = Str::slug($name);
        Log::info('Generated base slug', ['base_slug' => $slug]);
        
        // Check if the base slug exists
        $baseSlugExists = static::where('slug', $slug)->exists();
        Log::info('Base slug existence check', ['slug' => $slug, 'exists' => $baseSlugExists]);
        
        if (!$baseSlugExists) {
            Log::info('Base slug is unique, returning', ['final_slug' => $slug]);
            return $slug;
        }
        
        // Find the highest numbered suffix
        $existingSlugs = static::where('slug', 'LIKE', "{$slug}-%")
            ->pluck('slug');
        
        Log::info('Found existing slugs with pattern', [
            'pattern' => "{$slug}-%",
            'existing_slugs' => $existingSlugs->toArray()
        ]);
        
        $numberedSlugs = $existingSlugs->map(function ($existingSlug) use ($slug) {
                $suffix = str_replace("{$slug}-", '', $existingSlug);
                return is_numeric($suffix) ? (int) $suffix : 0;
            })
            ->filter()
            ->sort()
            ->values();
        
        Log::info('Processed numbered suffixes', ['numbered_suffixes' => $numberedSlugs->toArray()]);
        
        $nextNumber = $numberedSlugs->isEmpty() ? 1 : $numberedSlugs->last() + 1;
        $finalSlug = "{$slug}-{$nextNumber}";
        
        Log::info('Generated final unique slug', [
            'next_number' => $nextNumber,
            'final_slug' => $finalSlug
        ]);
        
        return $finalSlug;
    }

    /**
     * Get the brand name for file organization
     */
    public function getBrandNameAttribute(): string
    {
        return Str::slug($this->user->name);
    }

    /**
     * Get the full URL for the thumbnail
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }
        $url = asset('storage/' . $this->thumbnail_path);
        return str_replace('http://', 'https://', $url);
    }

    /**
     * Get a random video for A/B testing based on weights
     */
    public function getRandomVideo()
    {
        Log::info('Campaign::getRandomVideo called', ['campaign_id' => $this->id, 'campaign_name' => $this->name]);
        
        $videos = $this->activeVideos()->get();
        
        Log::info('Retrieved active videos', [
            'campaign_id' => $this->id,
            'video_count' => $videos->count(),
            'video_ids' => $videos->pluck('id')->toArray()
        ]);
        
        if ($videos->isEmpty()) {
            Log::warning('No active videos found for campaign', ['campaign_id' => $this->id]);
            return null;
        }
        
        $totalWeight = $videos->sum('weight');
        $random = rand(1, $totalWeight);
        
        Log::info('Weight-based selection initiated', [
            'campaign_id' => $this->id,
            'total_weight' => $totalWeight,
            'random_number' => $random,
            'video_weights' => $videos->pluck('weight', 'id')->toArray()
        ]);
        
        $currentWeight = 0;
        
        foreach ($videos as $video) {
            $currentWeight += $video->weight;
            Log::debug('Checking video weight', [
                'video_id' => $video->id,
                'video_weight' => $video->weight,
                'current_weight' => $currentWeight,
                'random_target' => $random
            ]);
            
            if ($random <= $currentWeight) {
                Log::info('Selected video for campaign', [
                    'campaign_id' => $this->id,
                    'selected_video_id' => $video->id,
                    'video_weight' => $video->weight,
                    'final_weight' => $currentWeight
                ]);
                return $video;
            }
        }
        
        Log::warning('Fallback to first video', [
            'campaign_id' => $this->id,
            'fallback_video_id' => $videos->first()->id
        ]);
        
        return $videos->first();
    }
    
}
