<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::table('user_transcripts', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('transcript_id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'folder_id']);
        });

        Schema::create('tag_user_transcript', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_transcript_id')->constrained()->cascadeOnDelete();

            $table->primary(['tag_id', 'user_transcript_id']);
            $table->index('user_transcript_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_user_transcript');

        Schema::table('user_transcripts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'folder_id']);
        });

        Schema::table('user_transcripts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('tags');
        Schema::dropIfExists('folders');
    }
};
