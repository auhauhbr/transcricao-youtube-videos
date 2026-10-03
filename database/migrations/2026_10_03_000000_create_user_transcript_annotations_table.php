<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_transcript_annotations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_transcript_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('start_ms');
            $table->string('type', 16);
            $table->text('text')->nullable();
            $table->timestamps();

            $table->unique(['user_transcript_id', 'start_ms', 'type']);
            $table->index(['user_transcript_id', 'type', 'start_ms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_transcript_annotations');
    }
};
