<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row settings table holding the runtime-configurable delayed-reveal
 * window. Edited from the admin's "Reveal delay" page; read by the app to
 * override its REVEAL_MIN_HOURS / REVEAL_MAX_HOURS env defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // always 1
            $table->decimal('reveal_min_hours', 5, 2)->default(12);
            $table->decimal('reveal_max_hours', 5, 2)->default(24);
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('app_settings')->insert([
            'id' => 1,
            'reveal_min_hours' => 12,
            'reveal_max_hours' => 24,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
