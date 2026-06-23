<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a display name and a password so admins can create/edit end users and so
 * the API can authenticate them. When you move to Supabase, the name maps to
 * raw_user_meta_data and the password to auth.users.encrypted_password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('email');
            $table->string('password')->nullable()->after('name');
            $table->rememberToken()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name', 'password', 'remember_token']);
        });
    }
};
