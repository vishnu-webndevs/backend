<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'video_file' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm|max:102400', // 100MB max
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'duration' => 'nullable|integer|min:1',
            'variant' => 'required|in:A,B',
            'is_active' => 'boolean',
            'cta_text' => 'nullable|string|max:100',
            'cta_url' => 'nullable|url|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'campaign_id.required' => 'Campaign is required.',
            'campaign_id.exists' => 'Selected campaign does not exist.',
            'name.required' => 'Video name is required.',
            'name.max' => 'Video name cannot exceed 255 characters.',
            'video_file.required' => 'Video file is required.',
            'video_file.file' => 'Video file must be a valid file.',
            'video_file.mimes' => 'Video file must be MP4, AVI, MOV, WMV, FLV, or WebM format.',
            'video_file.max' => 'Video file size cannot exceed 100MB.',
            'thumbnail.image' => 'Thumbnail must be an image file.',
            'thumbnail.mimes' => 'Thumbnail must be a JPEG, PNG, JPG, or GIF file.',
            'thumbnail.max' => 'Thumbnail size cannot exceed 2MB.',
            'duration.integer' => 'Duration must be a number.',
            'duration.min' => 'Duration must be at least 1 second.',
            'variant.required' => 'Video variant is required.',
            'variant.in' => 'Video variant must be either A or B.',
            'cta_text.max' => 'Call-to-action text cannot exceed 100 characters.',
            'cta_url.url' => 'Call-to-action URL must be a valid URL.',
            'cta_url.max' => 'Call-to-action URL cannot exceed 500 characters.',
        ];
    }
}
