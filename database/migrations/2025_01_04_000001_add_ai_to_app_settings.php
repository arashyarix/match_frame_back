<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds AI report-provider config to the single app_settings row:
 *   - ai_provider: 'mock' (built-in static) | 'anthropic' (Claude) | 'openai' (ChatGPT)
 *   - per-provider API key (encrypted at rest) + model name
 *
 * Guarded with hasColumn so it's safe on databases created before this change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'ai_provider')) {
                $table->string('ai_provider')->default('mock');
            }
            if (! Schema::hasColumn('app_settings', 'anthropic_api_key')) {
                $table->text('anthropic_api_key')->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'anthropic_model')) {
                $table->string('anthropic_model')->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'openai_api_key')) {
                $table->text('openai_api_key')->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'openai_model')) {
                $table->string('openai_model')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_provider', 'anthropic_api_key', 'anthropic_model',
                'openai_api_key', 'openai_model',
            ]);
        });
    }
};
