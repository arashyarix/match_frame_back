<?php

namespace App\Services;

use App\Models\AppSetting;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Thin wrapper over stripe-php that pulls keys from the admin-managed
 * app_settings row (not env), so you set Stripe keys in the panel.
 */
class StripeGateway
{
    public function __construct(private AppSetting $settings) {}

    public static function make(): self
    {
        return new self(AppSetting::singleton());
    }

    public function ready(): bool
    {
        return $this->settings->stripeReady();
    }

    public function client(): StripeClient
    {
        return new StripeClient((string) $this->settings->stripe_secret_key);
    }

    /** Create a Checkout Session for one analysis. */
    public function createCheckoutSession(string $analysisId, string $successUrl, string $cancelUrl): Session
    {
        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => (string) $this->settings->currency,
                    'unit_amount' => (int) $this->settings->price_cents,
                    'product_data' => [
                        'name' => 'MatchFrame photo analysis',
                        'description' => 'Audience ranking of your dating photos',
                    ],
                ],
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => ['analysisId' => $analysisId],
        ]);
    }

    /** Retrieve a Checkout Session (used to confirm payment on return). */
    public function retrieveSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId, []);
    }

    /** Verify + parse an incoming webhook using the stored signing secret. */
    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return Webhook::constructEvent(
            $payload,
            $sigHeader,
            (string) $this->settings->stripe_webhook_secret,
        );
    }
}
