<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stripe + pricing settings, edited from the admin's "Payments" page.
 * Secret values are encrypted at rest via casts on the AppSetting model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('stripe_enabled')->default(false)->after('reveal_max_hours');
            $table->text('stripe_publishable_key')->nullable()->after('stripe_enabled');
            $table->text('stripe_secret_key')->nullable()->after('stripe_publishable_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_secret_key');
            $table->integer('price_cents')->default(1200)->after('stripe_webhook_secret');
            $table->string('currency', 8)->default('usd')->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_enabled', 'stripe_publishable_key', 'stripe_secret_key',
                'stripe_webhook_secret', 'price_cents', 'currency',
            ]);
        });
    }
};
