<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class Analytics extends Model
{
    protected $fillable = [
        'video_id',
        'campaign_id',
        'event_type',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'country',
        'city',
        'referrer',
        'additional_data'
    ];

    protected $casts = [
        'additional_data' => 'array'
    ];

    /**
     * Get the video that owns the analytics
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Get the campaign that owns the analytics
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Create a new analytics record with automatic data detection
     */
    public static function track(string $eventType, ?int $videoId = null, ?int $campaignId = null, array $additionalData = []): self
    {
        $userAgent = Request::header('User-Agent');
        
        return static::create([
            'video_id' => $videoId,
            'campaign_id' => $campaignId,
            'event_type' => $eventType,
            'ip_address' => Request::ip(),
            'user_agent' => $userAgent,
            'device_type' => static::detectDeviceType($userAgent),
            'browser' => static::detectBrowser($userAgent),
            'os' => static::detectOS($userAgent),
            'referrer' => Request::header('Referer'),
            'additional_data' => $additionalData
        ]);
    }

    /**
     * Detect device type from user agent
     */
    private static function detectDeviceType(?string $userAgent): ?string
    {
        if (!$userAgent) return null;
        
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            if (preg_match('/iPad/', $userAgent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        return 'desktop';
    }

    /**
     * Detect browser from user agent
     */
    private static function detectBrowser(?string $userAgent): ?string
    {
        if (!$userAgent) return null;
        
        if (preg_match('/Chrome/', $userAgent)) return 'Chrome';
        if (preg_match('/Firefox/', $userAgent)) return 'Firefox';
        if (preg_match('/Safari/', $userAgent)) return 'Safari';
        if (preg_match('/Edge/', $userAgent)) return 'Edge';
        if (preg_match('/Opera/', $userAgent)) return 'Opera';
        
        return 'Unknown';
    }

    /**
     * Detect operating system from user agent
     */
    private static function detectOS(?string $userAgent): ?string
    {
        if (!$userAgent) return null;
        
        if (preg_match('/Windows/', $userAgent)) return 'Windows';
        if (preg_match('/Mac OS X/', $userAgent)) return 'macOS';
        if (preg_match('/Linux/', $userAgent)) return 'Linux';
        if (preg_match('/Android/', $userAgent)) return 'Android';
        if (preg_match('/iOS/', $userAgent)) return 'iOS';
        
        return 'Unknown';
    }
}
