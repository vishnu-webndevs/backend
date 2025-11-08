<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analytics;
use App\Models\Campaign;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    /**
     * Display analytics data with filtering and aggregation.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'video_id' => 'nullable|exists:videos,id',
            'event_type' => ['nullable', Rule::in(['video_play', 'video_view', 'video_complete', 'cta_click', 'page_view'])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => ['nullable', Rule::in(['day', 'week', 'month', 'event_type', 'country', 'device_type'])],
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);
        
        $query = Analytics::with(['video', 'campaign']);
        
        // Authorization: Users can only see analytics from their own campaigns
        $user = Auth::user();
        if (!$user->isAdmin()) {
            $query->whereHas('campaign', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        
        // Apply filters
        if (isset($validated['campaign_id'])) {
            $query->where('campaign_id', $validated['campaign_id']);
        }
        
        if (isset($validated['video_id'])) {
            $query->where('video_id', $validated['video_id']);
        }
        
        if (isset($validated['event_type'])) {
            $query->where('event_type', $validated['event_type']);
        }
        
        if (isset($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }
        
        if (isset($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }
        
        // Group by aggregation
        if (isset($validated['group_by'])) {
            return $this->getAggregatedData($query, $validated['group_by']);
        }
        
        $analytics = $query->orderBy('created_at', 'desc')
                          ->paginate($validated['per_page'] ?? 15);
        
        return response()->json($analytics);
    }

    /**
     * Store a new analytics event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'nullable|exists:videos,id',
            'campaign_id' => 'required|exists:campaigns,id',
            'event_type' => ['required', Rule::in(['video_play', 'video_view', 'video_complete', 'cta_click', 'page_view'])],
            'additional_data' => 'nullable|array'
        ]);
        
        // Use the Analytics model's track method for automatic data detection
        $analytics = Analytics::track(
            $validated['event_type'],
            $validated['campaign_id'],
            $validated['video_id'] ?? null,
            $validated['additional_data'] ?? []
        );
        
        return response()->json([
            'message' => 'Event tracked successfully',
            'data' => $analytics
        ], 201);
    }

    /**
     * Display general analytics summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'video_id' => 'nullable|exists:videos,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);
        
        $query = Analytics::query();
        
        // Authorization: Users can only see analytics from their own campaigns
        $user = Auth::user();
        if (!$user->isAdmin()) {
            $query->whereHas('campaign', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        
        // Apply filters
        if (isset($validated['campaign_id'])) {
            $query->where('campaign_id', $validated['campaign_id']);
        }
        
        if (isset($validated['video_id'])) {
            $query->where('video_id', $validated['video_id']);
        }
        
        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        
        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }
        
        $summary = [
            'total_video_views' => (clone $query)->where('event_type', 'video_play')->count(),
            'total_cta_clicks' => (clone $query)->where('event_type', 'cta_click')->count(),
            'total_page_views' => (clone $query)->where('event_type', 'page_view')->count(),
            'unique_visitors' => (clone $query)->distinct('ip_address')->count(),
            'conversion_rate' => $this->calculateOverallConversionRate($query),
        ];
        
        return response()->json($summary);
    }

    /**
     * Display analytics summary for a specific campaign.
     */
    public function campaignSummary(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);
        
        $summary = [
            'total_views' => $campaign->analytics()->where('event_type', 'video_play')->count(),
            'total_cta_clicks' => $campaign->analytics()->where('event_type', 'cta_click')->count(),
            'total_page_views' => $campaign->analytics()->where('event_type', 'page_view')->count(),
            'unique_visitors' => $campaign->analytics()->distinct('ip_address')->count(),
            'top_countries' => $campaign->analytics()
                ->select('country', DB::raw('count(*) as count'))
                ->whereNotNull('country')
                ->groupBy('country')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'device_breakdown' => $campaign->analytics()
                ->select('device_type', DB::raw('count(*) as count'))
                ->whereNotNull('device_type')
                ->groupBy('device_type')
                ->get(),
            'daily_stats' => $campaign->analytics()
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
        ];
        
        return response()->json([
            'data' => $summary
        ]);
    }

    /**
     * Display analytics summary for a specific video.
     */
    public function videoSummary(Video $video): JsonResponse
    {
        $this->authorize('view', $video);
        
        $summary = [
            'total_plays' => $video->analytics()->where('event_type', 'video_play')->count(),
            'total_cta_clicks' => $video->analytics()->where('event_type', 'cta_click')->count(),
            'conversion_rate' => $this->calculateConversionRate($video),
            'average_engagement' => $this->calculateEngagement($video),
            'top_referrers' => $video->analytics()
                ->select('referrer', DB::raw('count(*) as count'))
                ->whereNotNull('referrer')
                ->groupBy('referrer')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'hourly_distribution' => $video->analytics()
                ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
        ];
        
        return response()->json([
            'data' => $summary
        ]);
    }

    /**
     * Get aggregated analytics data.
     */
    private function getAggregatedData($query, string $groupBy): JsonResponse
    {
        switch ($groupBy) {
            case 'day':
                $data = $query->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as count')
                )->groupBy('date')->orderBy('date')->get();
                break;
                
            case 'week':
                $data = $query->select(
                    DB::raw('YEARWEEK(created_at) as week'),
                    DB::raw('count(*) as count')
                )->groupBy('week')->orderBy('week')->get();
                break;
                
            case 'month':
                $data = $query->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('count(*) as count')
                )->groupBy('year', 'month')->orderBy('year', 'month')->get();
                break;
                
            case 'event_type':
                $data = $query->select('event_type', DB::raw('count(*) as count'))
                    ->groupBy('event_type')->get();
                break;
                
            case 'country':
                $data = $query->select('country', DB::raw('count(*) as count'))
                    ->whereNotNull('country')
                    ->groupBy('country')
                    ->orderBy('count', 'desc')
                    ->get();
                break;
                
            case 'device_type':
                $data = $query->select('device_type', DB::raw('count(*) as count'))
                    ->whereNotNull('device_type')
                    ->groupBy('device_type')
                    ->get();
                break;
                
            default:
                $data = collect();
        }
        
        return response()->json([
            'data' => $data,
            'group_by' => $groupBy
        ]);
    }

    /**
     * Calculate overall conversion rate from a query.
     */
    private function calculateOverallConversionRate($query): float
    {
        $totalPlays = (clone $query)->where('event_type', 'video_play')->count();
        $totalClicks = (clone $query)->where('event_type', 'cta_click')->count();
        
        return $totalPlays > 0 ? round(($totalClicks / $totalPlays) * 100, 2) : 0.0;
    }

    /**
     * Calculate conversion rate for a video.
     */
    private function calculateConversionRate(Video $video): float
    {
        $totalPlays = $video->analytics()->where('event_type', 'video_play')->count();
        $totalClicks = $video->analytics()->where('event_type', 'cta_click')->count();
        
        return $totalPlays > 0 ? round(($totalClicks / $totalPlays) * 100, 2) : 0.0;
    }

    /**
     * Calculate engagement metrics for a video.
     */
    private function calculateEngagement(Video $video): array
    {
        $totalEvents = $video->analytics()->count();
        $uniqueVisitors = $video->analytics()->distinct('ip_address')->count();
        
        return [
            'total_events' => $totalEvents,
            'unique_visitors' => $uniqueVisitors,
            'events_per_visitor' => $uniqueVisitors > 0 ? round($totalEvents / $uniqueVisitors, 2) : 0
        ];
    }
}
