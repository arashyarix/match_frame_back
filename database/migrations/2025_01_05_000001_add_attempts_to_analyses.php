<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks processing attempts so the worker can auto-retry transient failures
 * (timeouts, rate limits) up to a cap, then give up and leave it for an admin.
 * Guarded with hasColumn so it's safe on existing databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('analyses', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0);
            }
            if (! Schema::hasColumn('analyses', 'last_tried_at')) {
                $table->timestamp('last_tried_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_tried_at']);
        });
    }
};
