<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // Drop the existing unique constraint on slug
            $table->dropUnique('videos_slug_unique');
            
            // Add a new unique constraint that includes both slug and campaign_id
            $table->unique(['slug', 'campaign_id'], 'videos_slug_campaign_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            // Drop the combined unique constraint
            $table->dropUnique('videos_slug_campaign_id_unique');
            
            // Restore the original unique constraint on slug only
            $table->unique('slug', 'videos_slug_unique');
        });
    }
};