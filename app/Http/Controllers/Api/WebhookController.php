<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $gateway = StripeGateway::make();

        if (! $gateway->ready()) {
            return response()->json(['error' => 'Stripe not configured.'], 400);
        }

        try {
            $event = $gateway->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $analysisId = $event->data->object->metadata->analysisId ?? null;

            if ($analysisId && ($analysis = Analysis::find($analysisId))) {
                // START THE DELAYED-REVEAL TIMER on confirmed payment.
                AnalysisController::markPaid($analysis);
            }
        }

        return response()->json(['received' => true]);
    }
}
