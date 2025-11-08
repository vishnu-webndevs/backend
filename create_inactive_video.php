<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Campaign;
use App\Models\Video;

// Find the campaign
$campaign = Campaign::where('slug', 'summer-sale-2024')->first();

if (!$campaign) {
    echo "Campaign 'summer-sale-2024' not found.\n";
    exit(1);
}

// Create an inactive video
$video = new Video();
$video->campaign_id = $campaign->id;
$video->title = 'Inactive Video';
$video->description = 'This is an inactive video for testing';
$video->file_path = 'https://example.com/inactive.mp4';
$video->thumbnail_path = 'https://example.com/inactive-thumb.jpg';
$video->status = 'inactive';
$video->slug = 'inactive-video';
$video->cta_text = 'Learn More';
$video->cta_url = 'https://example.com/learn-more';

try {
    $video->save();
    echo "Inactive video created successfully.\n";
} catch (\Exception $e) {
    echo "Error creating inactive video: " . $e->getMessage() . "\n";
}