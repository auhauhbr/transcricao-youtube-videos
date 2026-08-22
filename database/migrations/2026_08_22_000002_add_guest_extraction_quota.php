<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_usages', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->unsignedSmallInteger('used_slots')->default(0);
            $table->timestamps();
        });

        Schema::table('extractions', function (Blueprint $table) {
            $table->foreignId('guest_usage_id')->nullable()->after('user_id')->constrained();
            $table->timestampTz('guest_slot_released_at')->nullable()->after('guest_usage_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE guest_usages ADD CONSTRAINT guest_usages_used_slots_check CHECK (used_slots >= 0)');
            DB::statement('ALTER TABLE extractions ADD CONSTRAINT extractions_guest_release_check CHECK (guest_slot_released_at IS NULL OR guest_usage_id IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::table('extractions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_usage_id');
            $table->dropColumn('guest_slot_released_at');
        });

        Schema::dropIfExists('guest_usages');
    }
};
