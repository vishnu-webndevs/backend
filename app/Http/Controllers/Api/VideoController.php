<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\StoreVideoRequest;
use App\Http\Requests\UpdateVideoRequest;
use App\Http\Resources\VideoResource;
use App\Http\Resources\VideoCollection;
use Illuminate\Validation\Rule;

class VideoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Video::class);
        
        $query = Video::with(['campaign.user', 'analytics']);
        
        // Filter by campaign if provided
        if ($request->has('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }
        
        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        // Authorization: Users can only see videos from their own campaigns
        $user = Auth::user();
        if (!$user->isAdmin()) {
            $query->whereHas('campaign', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        
        $videos = $query->orderBy('created_at', 'desc')
                       ->paginate($request->get('per_page', 15));
        
        return (new VideoCollection($videos))->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVideoRequest $request): JsonResponse
    {
        $this->authorize('create', Video::class);
        
        $validated = $request->validated();
        
        // Check if user owns the campaign
        $campaign = Campaign::findOrFail($validated['campaign_id']);
        
        try {
            $this->authorize('update', $campaign);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => 'You are not authorized to add videos to this campaign.',
                'error' => 'insufficient_privileges',
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name
            ], 403);
        }
        
        // Map 'name' to 'title' for the Video model
        $validated['title'] = $validated['name'];
        unset($validated['name']);
        
        $validated['slug'] = Video::generateUniqueSlug($validated['title'], $validated['campaign_id']);
        
        // Set default weight if not provided
        if (!isset($validated['weight'])) {
            $validated['weight'] = 50;
        }
        
        // Create video first to get the ID
        $video = Video::create($validated);
        
        // Handle video file upload with proper naming
        if ($request->hasFile('video_file')) {
            $videoFile = $request->file('video_file');
            $user = Auth::user();
            $brandName = Str::slug($user->name);
            $extension = $videoFile->getClientOriginalExtension();
            $fileName = Str::slug($validated['title']) . '-' . $validated['campaign_id'] . '-' . $video->id . '.' . $extension;
            $filePath = $videoFile->storeAs("videos/{$brandName}/videos", $fileName, 'public');
            
            $video->update([
                'file_path' => $filePath,
                'mime_type' => $videoFile->getMimeType(),
                'file_size' => $videoFile->getSize(),
                'duration' => null // You might want to use a package like FFMpeg to get video duration
            ]);
        }
        
        // Handle thumbnail upload with proper naming
        if ($request->hasFile('thumbnail')) {
            $user = Auth::user();
            $brandName = Str::slug($user->name);
            $extension = $request->file('thumbnail')->getClientOriginalExtension();
            $fileName = Str::slug($validated['title']) . '-' . $validated['campaign_id'] . '-' . $video->id . '.' . $extension;
            $thumbnailPath = $request->file('thumbnail')
                ->storeAs("videos/{$brandName}/thumb", $fileName, 'public');
            
            $video->update(['thumbnail_path' => $thumbnailPath]);
        }
        
        $video->load(['campaign', 'analytics']);
        
        return (new VideoResource($video))
            ->additional([
                'message' => 'Video uploaded successfully'
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Video $video): JsonResponse
    {
        $this->authorize('view', $video);
        
        $video->load(['campaign.user', 'analytics']);
        
        return (new VideoResource($video))->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVideoRequest $request, Video $video): JsonResponse
    {
        $this->authorize('update', $video);
        
        $validated = $request->validated();
        
        // Update slug if name is changed
        if (isset($validated['name']) && $validated['name'] !== $video->title) {
            // Map 'name' to 'title' for the Video model
            $validated['title'] = $validated['name'];
            unset($validated['name']);
            $validated['slug'] = Video::generateUniqueSlug($validated['title'], $video->campaign_id);
        }
        
        // Handle video file upload
        if ($request->hasFile('video_file')) {
            // Delete old video file
            if ($video->file_path) {
                Storage::disk('public')->delete($video->file_path);
            }
            
            $videoFile = $request->file('video_file');
            $user = Auth::user();
            $brandName = Str::slug($user->name);
            $extension = $videoFile->getClientOriginalExtension();
            $title = isset($validated['title']) ? $validated['title'] : $video->title;
            $fileName = Str::slug($title) . '-' . $video->campaign_id . '-' . $video->id . '.' . $extension;
            $validated['file_path'] = $videoFile->storeAs("videos/{$brandName}/videos", $fileName, 'public');
            $validated['mime_type'] = $videoFile->getMimeType();
            $validated['file_size'] = $videoFile->getSize();
            $validated['duration'] = null; // Reset duration for new file
        } else if (!$video->file_path) {
            // If no file_path exists and no new video is uploaded, return an error
            return response()->json([
                'message' => 'Video file is required',
                'errors' => ['video_file' => 'Please upload a video file']
            ], 422);
        }
        
        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if exists
            if ($video->thumbnail_path) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }
            $user = Auth::user();
            $brandName = Str::slug($user->name);
            $extension = $request->file('thumbnail')->getClientOriginalExtension();
            $title = isset($validated['title']) ? $validated['title'] : $video->title;
            $fileName = Str::slug($title) . '-' . $video->campaign_id . '-' . $video->id . '.' . $extension;
            $validated['thumbnail_path'] = $request->file('thumbnail')
                ->storeAs("videos/{$brandName}/thumb", $fileName, 'public');
        }
        
        $video->update($validated);
        $video->load(['campaign', 'analytics']);
        
        return (new VideoResource($video))
            ->additional([
                'message' => 'Video updated successfully'
            ])
            ->response(); // Add ->response() to return JsonResponse
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Video $video): JsonResponse
    {
        $this->authorize('delete', $video);
        
        // Delete video file if exists
        if ($video->file_path) {
            Storage::disk('public')->delete($video->file_path);
        }
        
        // Delete thumbnail if exists
        if ($video->thumbnail_path) {
            Storage::disk('public')->delete($video->thumbnail_path);
        }
        
        $video->delete();
        
        return response()->json([
            'message' => 'Video deleted successfully'
        ]);
    }
}
