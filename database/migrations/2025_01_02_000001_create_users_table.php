<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The app's end users (the men who upload photos).
 *
 * UUID id keeps parity with Supabase auth.users so switching later is seamless.
 * Only the columns the admin needs are modelled here; the real app may add more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->timestamp('email_confirmed_at')->nullable();
            $table->timestamp('last_sign_in_at')->nullable();
            $table->json('raw_user_meta_data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
