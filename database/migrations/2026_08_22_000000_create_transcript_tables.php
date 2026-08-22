<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 35);
            $table->string('language_name')->nullable();
            $table->string('source', 32);
            $table->unsignedBigInteger('word_count');
            $table->unsignedBigInteger('character_count');
            $table->timestampTz('extracted_at');
            $table->timestamps();

            $table->unique(['video_id', 'language_code', 'source']);
        });

        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->text('text');
            $table->timestamps();

            $table->unique(['transcript_id', 'position']);
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->string('source', 32);
            $table->timestamps();

            $table->unique(['transcript_id', 'position']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transcript_segments ADD CONSTRAINT transcript_segments_time_check CHECK (start_ms >= 0 AND end_ms >= start_ms)');
            DB::statement('ALTER TABLE transcript_segments ADD CONSTRAINT transcript_segments_text_check CHECK (length(btrim(text)) > 0)');
            DB::statement('ALTER TABLE chapters ADD CONSTRAINT chapters_time_check CHECK (start_ms >= 0 AND end_ms >= start_ms)');
            DB::statement('ALTER TABLE chapters ADD CONSTRAINT chapters_title_check CHECK (length(btrim(title)) > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('transcript_segments');
        Schema::dropIfExists('transcripts');
    }
};
