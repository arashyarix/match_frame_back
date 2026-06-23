<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table (id = 1). Holds:
 *   - the delayed-reveal window (reveal_min_hours / reveal_max_hours)
 *   - Stripe keys + pricing
 *
 * The Next.js frontend reads the public bits (publishable key, price, reveal
 * window) via GET /api/config; the API uses the secret key + webhook secret for
 * checkout and webhook verification. Secrets are encrypted at rest.
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'reveal_min_hours'      => 'float',
        'reveal_max_hours'      => 'float',
        'updated_at'            => 'datetime',
        'stripe_enabled'        => 'boolean',
        'price_cents'           => 'integer',
        // Encrypted at rest so the DB never stores raw secrets.
        'stripe_secret_key'     => 'encrypted',
        'stripe_webhook_secret' => 'encrypted',
        'anthropic_api_key'     => 'encrypted',
        'openai_api_key'        => 'encrypted',
        'google_client_secret'  => 'encrypted',
    ];

    /** The one and only settings row, created if missing. */
    public static function singleton(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            ['reveal_min_hours' => 12, 'reveal_max_hours' => 24, 'updated_at' => now()],
        );
    }

    /** Is Stripe usable (enabled + secret key present)? */
    public function stripeReady(): bool
    {
        if (! (bool) $this->stripe_enabled) {
            return false;
        }
        // The secret is an encrypted cast; a rotated/changed APP_KEY would make
        // it undecryptable. Never let that 500 the public config endpoint.
        try {
            return filled($this->stripe_secret_key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Publishable key, safe against decrypt issues (it isn't encrypted, but be defensive). */
    public function publishableKey(): ?string
    {
        try {
            return $this->stripe_publishable_key ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Price as a display string, e.g. "$12.00". */
    public function priceDisplay(): string
    {
        $symbol = strtolower((string) $this->currency) === 'usd' ? '$' : '';
        return $symbol.number_format(((int) $this->price_cents) / 100, 2)
            .($symbol ? '' : ' '.strtoupper((string) $this->currency));
    }

    // ── AI report provider ────────────────────────────────────────────────

    /** Active provider: 'mock' (built-in), 'anthropic' (Claude), 'openai' (ChatGPT). */
    public function aiProvider(): string
    {
        $p = (string) ($this->ai_provider ?: 'mock');
        return in_array($p, ['mock', 'anthropic', 'openai'], true) ? $p : 'mock';
    }

    /** Decrypted API key for the active provider (null-safe). */
    public function aiKey(): ?string
    {
        try {
            return match ($this->aiProvider()) {
                'anthropic' => $this->anthropic_api_key ?: null,
                'openai'    => $this->openai_api_key ?: null,
                default     => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Model name for the active provider, with sensible defaults. */
    public function aiModel(): string
    {
        return match ($this->aiProvider()) {
            'anthropic' => (string) ($this->anthropic_model ?: 'claude-sonnet-4-6'),
            'openai'    => (string) ($this->openai_model ?: 'gpt-4o'),
            default     => '',
        };
    }

    /** Should we call a real model? (a provider is selected AND its key is set) */
    public function aiReady(): bool
    {
        return $this->aiProvider() !== 'mock' && filled($this->aiKey());
    }

    // ── Google sign-in (OAuth) ────────────────────────────────────────────

    public function googleClientId(): ?string
    {
        try {
            return $this->google_client_id ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function googleClientSecret(): ?string
    {
        try {
            return $this->google_client_secret ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Is Google sign-in enabled AND fully configured? */
    public function googleReady(): bool
    {
        return (bool) $this->google_enabled
            && filled($this->googleClientId())
            && filled($this->googleClientSecret());
    }
}
