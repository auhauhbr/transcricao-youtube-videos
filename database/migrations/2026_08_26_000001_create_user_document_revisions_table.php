<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('revision_number');
            $table->string('kind', 24);
            $table->string('title');
            $table->jsonb('content');
            $table->unsignedBigInteger('document_lock_version');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_document_id', 'revision_number']);
            $table->index(['user_document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_document_revisions');
    }
};
