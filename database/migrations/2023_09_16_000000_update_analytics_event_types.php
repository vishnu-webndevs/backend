<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, modify the enum to allow the new values
        DB::statement("ALTER TABLE analytics MODIFY COLUMN event_type ENUM('video_play', 'video_view', 'video_complete', 'cta_click', 'page_view') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to the original enum values
        DB::statement("ALTER TABLE analytics MODIFY COLUMN event_type ENUM('video_play', 'cta_click', 'page_view') NOT NULL");
    }
};