<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google sign-in (OAuth) config on the single app_settings row. The client
 * secret is encrypted at rest. Guarded with hasColumn for existing databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('app_settings', 'google_enabled')) {
                $table->boolean('google_enabled')->default(false);
            }
            if (! Schema::hasColumn('app_settings', 'google_client_id')) {
                $table->text('google_client_id')->nullable();
            }
            if (! Schema::hasColumn('app_settings', 'google_client_secret')) {
                $table->text('google_client_secret')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['google_enabled', 'google_client_id', 'google_client_secret']);
        });
    }
};
