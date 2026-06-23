<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\Carbon;

/**
 * Reads the runtime settings (reveal window, price, Stripe) and computes a
 * randomized reveal time. Mirrors the Next.js reveal logic so behaviour is
 * identical whichever side assigns reveal_at.
 */
class Settings
{
    public static function get(): AppSetting
    {
        return AppSetting::singleton();
    }

    /**
     * A random reveal moment within [reveal_min_hours, reveal_max_hours] from
     * $from (default: now). Set both bounds equal for a fixed delay.
     */
    public static function computeRevealAt(?Carbon $from = null): Carbon
    {
        $s = self::get();
        $from = $from ?? now();

        $min = (float) $s->reveal_min_hours;
        $max = (float) $s->reveal_max_hours;
        $lo = min($min, $max);
        $hi = max($min, $max);

        $hours = $lo + (mt_rand() / mt_getrandmax()) * ($hi - $lo);

        return $from->copy()->addRealSeconds((int) round($hours * 3600));
    }

    /** Public config the frontend is allowed to see. */
    public static function publicConfig(): array
    {
        $s = self::get();

        return [
            'price_cents'            => (int) $s->price_cents,
            'currency'               => (string) $s->currency,
            'price_display'          => $s->priceDisplay(),
            'reveal_min_hours'       => (float) $s->reveal_min_hours,
            'reveal_max_hours'       => (float) $s->reveal_max_hours,
            'stripe_enabled'         => $s->stripeReady(),
            'stripe_publishable_key' => $s->stripeReady() ? $s->publishableKey() : null,
        ];
    }
}
