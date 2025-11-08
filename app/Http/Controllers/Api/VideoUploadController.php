<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VideoUploadController extends Controller
{
    /**
     * Handle video file upload with progress tracking
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'video_file' => 'required|file|mimes:mp4,webm,avi|max:102400', // 100MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $videoFile = $request->file('video_file');
            $originalName = $videoFile->getClientOriginalName();
            $extension = $videoFile->getClientOriginalExtension();
            
            // Generate a unique filename
            $fileName = Str::uuid() . '.' . $extension;
            $brandName = Str::slug($user->name);
            
            // Store the file
            $path = $videoFile->storeAs("videos/{$brandName}", $fileName, 'public');
            
            // Generate the public URL
            $fileUrl = Storage::url($path);
            
            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully',
                'data' => [
                    'file_url' => $fileUrl,
                    'file_name' => $originalName,
                    'file_size' => $videoFile->getSize(),
                    'mime_type' => $videoFile->getMimeType(),
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload video',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}