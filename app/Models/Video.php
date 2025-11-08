<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Video extends Model
{
    protected $fillable = [
        'campaign_id',
        'title',
        'description',
        'slug',
        'file_path',
        'thumbnail_path',
        'cta_text',
        'cta_url',
        'weight',
        'mime_type',
        'file_size',
        'duration',
        'views',
        'status'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
        'weight' => 'integer',
        'views' => 'integer'
    ];

    protected $appends = [
        'thumbnail_url',
        'file_url'
    ];

    /**
     * Get the campaign that owns the video
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get analytics for the video
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(Analytics::class);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($video) {
            if (empty($video->slug)) {
                $video->slug = static::generateUniqueSlug($video->title, $video->campaign_id);
            }
        });
        
        static::updating(function ($video) {
            if ($video->isDirty('title') && empty($video->slug)) {
                $video->slug = static::generateUniqueSlug($video->title, $video->campaign_id);
            }
        });
    }

    /**
     * Generate a unique slug for the video
     * @param string $title The video title
     * @param int|null $campaignId The campaign ID (optional)
     * @return string
     */
    public static function generateUniqueSlug(string $title, ?int $campaignId = null): string
    {
        $slug = Str::slug($title);
        
        // If campaign ID is provided, make it part of the slug to ensure uniqueness across campaigns
        if ($campaignId) {
            $baseSlug = "{$slug}-{$campaignId}";
            $count = static::where('slug', 'LIKE', "{$baseSlug}%")->count();
            return $count ? "{$baseSlug}-{$count}" : $baseSlug;
        }
        
        // Fallback to original behavior if no campaign ID is provided
        $count = static::where('slug', 'LIKE', "{$slug}%")->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }

    /**
     * Get the brand name for file organization
     */
    public function getBrandNameAttribute(): string
    {
        return Str::slug($this->campaign->user->name);
    }

    /**
     * Generate the expected file name for this video
     */
    public function getExpectedFileNameAttribute(): string
    {
        $extension = pathinfo($this->file_path, PATHINFO_EXTENSION);
        return Str::slug($this->title) . '-' . $this->campaign_id . '-' . $this->id . '.' . $extension;
    }

    /**
     * Generate the expected thumbnail file name for this video
     */
    public function getExpectedThumbnailNameAttribute(): string
    {
        if (!$this->thumbnail_path) return '';
        $extension = pathinfo($this->thumbnail_path, PATHINFO_EXTENSION);
        return Str::slug($this->title) . '-' . $this->campaign_id . '-' . $this->id . '.' . $extension;
    }

    /**
     * Get the full URL for the video file
     */
    public function getFileUrlAttribute(): string
    {
        $url = asset('storage/' . $this->file_path);
        return str_replace('http://', 'https://', $url);
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
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }
        
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) {
            return 'Unknown';
        }
        
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;
        
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
