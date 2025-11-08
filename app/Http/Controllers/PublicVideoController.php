<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class PublicVideoController extends Controller
{
    /**
     * Display a video by its slug for public viewing
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        try {
            // Find video by slug
            $video = Video::with(['campaign'])
                ->where('slug', $slug)
                ->where('status', 'active')
                ->first();

            if (!$video) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video not found or inactive'
                ], 404);
            }

            // Check if campaign is active
            if (!$video->campaign->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign is not active'
                ], 404);
            }

            // A/B Testing Logic
            $variant = $this->determineVariant($request, $video);

            // Apply variant-specific modifications if needed
            $videoData = $this->applyVariantModifications($video, $variant);

            return response()->json([
                'success' => true,
                'data' => [
                    'video' => $videoData,
                    'variant' => $variant
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the video'
            ], 500);
        }
    }

    /**
     * Determine A/B test variant for the user
     */
    private function determineVariant(Request $request, Video $video): ?string
    {
        // Check if A/B testing is enabled for this campaign
        $settings = $video->campaign->settings ? json_decode($video->campaign->settings, true) : [];
        
        if (!isset($settings['ab_testing_enabled']) || !$settings['ab_testing_enabled']) {
            return null;
        }

        // Check for existing variant in session/cookie
        $sessionKey = 'ab_variant_' . $video->campaign->id;
        
        if ($request->session()->has($sessionKey)) {
            return $request->session()->get($sessionKey);
        }

        // Determine variant based on user identifier (IP + User Agent hash)
        $userIdentifier = $request->ip() . $request->userAgent();
        $hash = crc32($userIdentifier . $video->campaign->id);
        
        // Split traffic 50/50 between variants A and B
        $variant = ($hash % 2 === 0) ? 'A' : 'B';
        
        // Store variant in session for consistency
        $request->session()->put($sessionKey, $variant);
        
        return $variant;
    }

    /**
     * Apply variant-specific modifications to video data
     */
    private function applyVariantModifications(Video $video, ?string $variant): array
    {
        $videoData = $video->toArray();
        
        if (!$variant) {
            return $videoData;
        }

        // Example A/B test modifications
        if ($variant === 'B') {
            // Variant B might have different CTA text or styling
            if ($videoData['cta_text']) {
                // You can modify CTA text for variant B
                // $videoData['cta_text'] = $this->getVariantBCtaText($videoData['cta_text']);
            }
        }

        return $videoData;
    }

    /**
     * Get video metadata for SEO (used by frontend for meta tags)
     */
    public function metadata(string $slug): JsonResponse
    {
        try {
            $video = Video::with(['campaign'])
                ->where('slug', $slug)
                ->where('status', 'active')
                ->first();

            if (!$video || $video->campaign->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Video not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'title' => $video->title,
                    'description' => $video->description,
                    'thumbnail' => $video->thumbnail_path,
                    'video_url' => $video->file_path,
                    'campaign_name' => $video->campaign->name,
                    'duration' => $video->duration,
                    'mime_type' => $video->mime_type
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching video metadata'
            ], 500);
        }
    }

    /**
     * Generate sitemap for public videos
     */
    public function sitemap(): \Illuminate\Http\Response
    {
        $videos = Video::with(['campaign'])
            ->where('status', 'active')
            ->whereHas('campaign', function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($videos as $video) {
            $sitemap .= '  <url>' . "\n";
            $sitemap .= '    <loc>' . url('/watch/' . $video->slug) . '</loc>' . "\n";
            $sitemap .= '    <lastmod>' . $video->updated_at->format('Y-m-d\TH:i:s\Z') . '</lastmod>' . "\n";
            $sitemap .= '    <changefreq>weekly</changefreq>' . "\n";
            $sitemap .= '    <priority>0.8</priority>' . "\n";
            $sitemap .= '  </url>' . "\n";
        }

        $sitemap .= '</urlset>';

        return response($sitemap, 200, [
            'Content-Type' => 'application/xml'
        ]);
    }

    /**
     * Get popular videos for homepage or recommendations
     */
    public function popular(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            
            $videos = Video::with(['campaign'])
                ->where('status', 'active')
                ->whereHas('campaign', function ($query) {
                    $query->where('is_active', true);
                })
                ->orderBy('views', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($video) {
                    return [
                        'slug' => $video->slug,
                        'title' => $video->title,
                        'description' => $video->description,
                        'thumbnail' => $video->thumbnail_path,
                        'views' => $video->views,
                        'campaign_name' => $video->campaign->name
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $videos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching popular videos'
            ], 500);
        }
    }

    /**
     * Get campaign videos in round-robin fashion
     */
    public function campaignRoundRobin(Request $request, string $brand_username, string $campaign_slug): JsonResponse
    {
        try {
            // Find brand by username
            $brand = User::where('username', $brand_username)->first();
            if (!$brand) {
                return response()->json([
                    'success' => false,
                    'message' => 'Brand not found'
                ], 404);
            }

            // Find campaign by slug and brand
            $campaign = Campaign::where('slug', $campaign_slug)
                ->where('user_id', $brand->id)
                ->where('is_active', true)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign not found or inactive'
                ], 404);
            }

            // Get all videos from campaign, regardless of status
            $videos = Video::where('campaign_id', $campaign->id)
                ->orderBy('id')
                ->get();

            if ($videos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No videos found in this campaign'
                ], 404);
            }

            // Round-robin logic using cache
            $cacheKey = "campaign_round_robin_{$campaign->id}";
            $currentIndex = Cache::get($cacheKey, 0);
            
            // Get current video
            $currentVideo = $videos[$currentIndex % $videos->count()];
            
            // Increment counter for next request
            Cache::put($cacheKey, ($currentIndex + 1) % $videos->count(), 3600); // Cache for 1 hour

            return response()->json([
                'success' => true,
                'data' => [
                    'campaign' => [
                        'id' => $campaign->id,
                        'name' => $campaign->name,
                        'slug' => $campaign->slug,
                        'description' => $campaign->description
                    ],
                    'video' => [
                        'id' => $currentVideo->id,
                        'title' => $currentVideo->title,
                        'description' => $currentVideo->description,
                        'file_path' => $currentVideo->file_path,
                        'thumbnail_path' => $currentVideo->thumbnail_path,
                        'duration' => $currentVideo->duration,
                        'cta_text' => $currentVideo->cta_text,
                        'cta_url' => $currentVideo->cta_url,
                        'slug' => $currentVideo->slug
                    ],
                    'total_videos' => $videos->count(),
                    'current_index' => $currentIndex % $videos->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching campaign videos'
            ], 500);
        }
    }

    /**
     * Get specific video by brand, campaign, and video slug
     */
    public function brandVideo(Request $request, string $brand_username, string $campaign_slug, string $video_slug): JsonResponse
    {
        try {
            // Find brand by username
            $brand = User::where('username', $brand_username)->first();
            if (!$brand) {
                return response()->json([
                    'success' => false,
                    'message' => 'Brand not found'
                ], 404);
            }

            // Find campaign by slug and brand
            $campaign = Campaign::where('slug', $campaign_slug)
                ->where('user_id', $brand->id)
                ->where('is_active', true)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign not found or inactive'
                ], 404);
            }

            // Find video by slug and campaign, regardless of status
            $video = Video::where('slug', $video_slug)
                ->where('campaign_id', $campaign->id)
                ->first();

            if (!$video) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video not found'
                ], 404);
            }

            // Increment view count
            $video->increment('views');

            return response()->json([
                'success' => true,
                'data' => [
                    'campaign' => [
                        'id' => $campaign->id,
                        'name' => $campaign->name,
                        'slug' => $campaign->slug,
                        'description' => $campaign->description
                    ],
                    'video' => [
                        'id' => $video->id,
                        'title' => $video->title,
                        'description' => $video->description,
                        'file_path' => $video->file_path,
                        'thumbnail_path' => $video->thumbnail_path,
                        'duration' => $video->duration,
                        'cta_text' => $video->cta_text,
                        'cta_url' => $video->cta_url,
                        'slug' => $video->slug,
                        'views' => $video->views
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the video'
            ], 500);
        }
    }


    public function allCampaigns()
    {
        // Fetch all campaigns directly (no Brand model)
        $campaigns = \App\Models\Campaign::select('brand_username', 'slug')
            ->get()
            ->map(function ($campaign) {
                return [
                    'brand_username' => $campaign->brand_username,
                    'campaign_name' => $campaign->slug, // match frontend key
                ];
            });

        return response()->json($campaigns);
    }
}