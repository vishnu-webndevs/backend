<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'cta_text' => 'required|string|max:100',
            'cta_url' => 'required|url|max:500',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
            'settings.autoplay' => 'boolean',
            'settings.loop' => 'boolean',
            'settings.controls' => 'boolean',
            'settings.muted' => 'boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Campaign name is required.',
            'name.max' => 'Campaign name cannot exceed 255 characters.',
            'cta_text.required' => 'Call-to-action text is required.',
            'cta_text.max' => 'Call-to-action text cannot exceed 100 characters.',
            'cta_url.required' => 'Call-to-action URL is required.',
            'cta_url.url' => 'Call-to-action URL must be a valid URL.',
            'thumbnail.image' => 'Thumbnail must be an image file.',
            'thumbnail.mimes' => 'Thumbnail must be a JPEG, PNG, JPG, or GIF file.',
            'thumbnail.max' => 'Thumbnail size cannot exceed 2MB.',
        ];
    }
}
