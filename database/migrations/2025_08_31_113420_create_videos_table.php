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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->string('title'); // Changed from 'name' to 'title'
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->string('file_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('cta_text')->nullable(); // Call-to-action text
            $table->string('cta_url')->nullable(); // Call-to-action URL
            $table->integer('weight')->default(1); // For A/B testing
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->bigInteger('views')->default(0); // View count
            $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
