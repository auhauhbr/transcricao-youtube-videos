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
            $table->string('provider', 32);
            $table->string('provider_video_id', 64);
            $table->string('title')->nullable();
            $table->string('channel_name')->nullable();
            $table->string('channel_id')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_video_id']);
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
