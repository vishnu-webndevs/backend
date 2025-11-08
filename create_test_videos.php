<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Campaign;
use App\Models\Video;
use Illuminate\Support\Str;

// Find the campaign
$campaign = Campaign::where('slug', 'summer-sale-2024')->first();

if (!$campaign) {
    echo "Campaign not found\n";
    exit(1);
}

echo "Creating test videos for campaign: {$campaign->name}\n";

// Create test videos
for ($i = 1; $i <= 3; $i++) {
    $video = new Video();
    $video->campaign_id = $campaign->id;
    $video->title = "Test Video {$i}";
    $video->description = "Test video description {$i}";
    $video->file_path = "https://example.com/video{$i}.mp4";
    $video->thumbnail_path = "https://example.com/thumb{$i}.jpg";
    $video->status = "active";
    $video->slug = "test-video-{$i}";
    $video->cta_text = "Learn More";
    $video->cta_url = "https://example.com/learn-more";
    $video->save();
    
    echo "Created video: {$video->title}\n";
}

echo "Done!\n";