<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);
        
        $user = Auth::user();
        
        Log::info('CampaignController::index called', [
            'user_id' => $user->id,
            'user_role' => $user->role,
            'request_params' => $request->all()
        ]);
        
        $query = Campaign::with(['user', 'videos']);
        
        // Filter by user role
        if ($user->isBrand()) {
            Log::info('Filtering campaigns for brand user', ['user_id' => $user->id]);
            $query->where('user_id', $user->id);
        } elseif ($user->isAgency()) {
            // Agency can see campaigns from their brand users
            $brandUsers = $user->brandUsers()->pluck('id');
            Log::info('Filtering campaigns for agency user', [
                'user_id' => $user->id,
                'brand_users' => $brandUsers->toArray()
            ]);
            $query->whereIn('user_id', $brandUsers->push($user->id));
        }
        
        // Apply filters
        if ($request->has('is_active')) {
            $isActive = $request->boolean('is_active');
            Log::info('Applying is_active filter', ['is_active' => $isActive]);
            $query->where('is_active', $isActive);
        }
        
        if ($request->has('search')) {
            $search = $request->get('search');
            Log::info('Applying search filter', ['search_term' => $search]);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $campaigns = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));
        
        Log::info('Campaigns retrieved successfully', [
            'total_campaigns' => $campaigns->total(),
            'current_page' => $campaigns->currentPage(),
            'per_page' => $campaigns->perPage()
        ]);
        
        return response()->json($campaigns);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);
        
        $user = Auth::user();
        
        Log::info('CampaignController::store called', [
            'user_id' => $user->id,
            'request_data' => $request->except(['thumbnail']) // Exclude file from log
        ]);
        
        $validated = $request->validated();
        
        $validated['user_id'] = $user->id;
        
        Log::info('Generating slug for new campaign', ['campaign_name' => $validated['name']]);
        $validated['slug'] = Campaign::generateSlug($validated['name']);
        Log::info('Generated slug for campaign', ['generated_slug' => $validated['slug']]);
        
        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $brandName = Str::slug($user->name);
            Log::info('Processing thumbnail upload', [
                'brand_name' => $brandName,
                'file_size' => $request->file('thumbnail')->getSize(),
                'file_type' => $request->file('thumbnail')->getMimeType()
            ]);
            
            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->store("campaigns/{$brandName}/thumb", 'public');
            
            Log::info('Thumbnail uploaded successfully', [
                'thumbnail_path' => $validated['thumbnail_path']
            ]);
        }
        
        Log::info('Creating campaign with validated data', [
            'validated_data' => Arr::except($validated, ['thumbnail_path']) // Don't log file paths
        ]);
        
        $campaign = Campaign::create($validated);
        $campaign->load(['user', 'videos']);
        
        Log::info('Campaign created successfully', [
            'campaign_id' => $campaign->id,
            'campaign_slug' => $campaign->slug,
            'campaign_name' => $campaign->name
        ]);
        
        return response()->json([
            'message' => 'Campaign created successfully',
            'data' => $campaign
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Campaign $campaign): JsonResponse
    {
        $user = Auth::user();
        
        Log::info('CampaignController::show called', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'campaign_slug' => $campaign->slug
        ]);
        
        Log::info('Authorizing campaign view access', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'campaign_owner_id' => $campaign->user_id
        ]);
        
        try {
            $this->authorize('view', $campaign);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Campaign view access denied', [
                'user_id' => $user->id,
                'user_role' => $user->role,
                'campaign_id' => $campaign->id,
                'campaign_owner_id' => $campaign->user_id
            ]);
            
            return response()->json([
                'message' => 'You are not authorized to view this campaign.',
                'error' => 'insufficient_privileges',
                'campaign_id' => $campaign->id
            ], 403);
        }
        
        Log::info('Loading campaign relationships', ['campaign_id' => $campaign->id]);
        $campaign->load(['user', 'videos.analytics', 'analytics']);
        
        Log::info('Campaign retrieved successfully', [
            'campaign_id' => $campaign->id,
            'videos_count' => $campaign->videos->count(),
            'analytics_count' => $campaign->analytics->count()
        ]);
        
        return response()->json([
            'data' => $campaign
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        $user = Auth::user();
        
        Log::info('CampaignController::update called', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'campaign_slug' => $campaign->slug,
            'request_data' => $request->except(['thumbnail']) // Exclude file from log
        ]);
        
        Log::info('Authorizing campaign update access', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'campaign_owner_id' => $campaign->user_id
        ]);
        
        $this->authorize('update', $campaign);
        
        $validated = $request->validated();
        
        // Update slug if name is changed
        if (isset($validated['name']) && $validated['name'] !== $campaign->name) {
            Log::info('Campaign name changed, generating new slug', [
                'old_name' => $campaign->name,
                'new_name' => $validated['name'],
                'old_slug' => $campaign->slug
            ]);
            
            $validated['slug'] = Campaign::generateSlug($validated['name']);
            
            Log::info('Generated new slug for updated campaign', [
                'new_slug' => $validated['slug']
            ]);
        }
        
        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            Log::info('Processing thumbnail update', [
                'campaign_id' => $campaign->id,
                'has_existing_thumbnail' => !empty($campaign->thumbnail_path)
            ]);
            
            // Delete old thumbnail if exists
            if ($campaign->thumbnail_path) {
                Log::info('Deleting old thumbnail', [
                    'old_thumbnail_path' => $campaign->thumbnail_path
                ]);
                Storage::disk('public')->delete($campaign->thumbnail_path);
            }
            
            $brandName = Str::slug($user->name);
            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->store("campaigns/{$brandName}/thumb", 'public');
            
            Log::info('New thumbnail uploaded', [
                'new_thumbnail_path' => $validated['thumbnail_path']
            ]);
        }
        
        Log::info('Updating campaign with validated data', [
            'campaign_id' => $campaign->id,
            'changes' => array_keys($validated)
        ]);
        
        $campaign->update($validated);
        $campaign->load(['user', 'videos']);
        
        Log::info('Campaign updated successfully', [
            'campaign_id' => $campaign->id,
            'updated_fields' => array_keys($validated)
        ]);
        
        return response()->json([
            'message' => 'Campaign updated successfully',
            'data' => $campaign
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);
        
        // Delete thumbnail if exists
        if ($campaign->thumbnail_path) {
            Storage::disk('public')->delete($campaign->thumbnail_path);
        }
        
        // Delete associated videos and their files
        foreach ($campaign->videos as $video) {
            if ($video->file_path) {
                Storage::disk('public')->delete($video->file_path);
            }
            if ($video->thumbnail_path) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }
        }
        
        $campaign->delete();
        
        return response()->json([
            'message' => 'Campaign deleted successfully'
        ]);
    }
}
