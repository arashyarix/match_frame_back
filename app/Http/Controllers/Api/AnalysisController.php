<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnalysisResource;
use App\Models\Analysis;
use App\Models\Payment;
use App\Models\Photo;
use App\Services\Settings;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalysisController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Analysis::where('user_id', $request->user()->id)
            ->with('photos')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => AnalysisResource::collection($items)]);
    }

    /**
     * Create an analysis and upload its photos (multipart/form-data).
     * Fields: name?, audience, photos[] (image files).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['nullable', 'string', 'max:120'],
            'audience' => ['required', 'string', 'max:16'],
            'photos'   => ['required', 'array', 'min:2', 'max:12'],
            'photos.*' => ['image', 'max:10240'], // 10 MB each
        ]);

        $user = $request->user();
        $analysisId = (string) Str::uuid();

        $analysis = DB::transaction(function () use ($request, $data, $user, $analysisId) {
            $analysis = Analysis::create([
                'id'          => $analysisId,
                'user_id'     => $user->id,
                'name'        => $data['name'] ?? 'Photo test',
                'status'      => 'draft',
                'audience'    => $data['audience'],
                'photo_count' => count($data['photos']),
                'created_at'  => now(),
            ]);

            foreach ($request->file('photos') as $i => $file) {
                $path = $file->store("{$user->id}/{$analysisId}", 'public');
                Photo::create([
                    'id'           => (string) Str::uuid(),
                    'analysis_id'  => $analysisId,
                    'position'     => $i + 1,
                    'storage_path' => $path,
                    'created_at'   => now(),
                ]);
            }

            return $analysis;
        });

        return response()->json(['data' => new AnalysisResource($analysis)], 201);
    }

    public function show(Request $request, Analysis $analysis): JsonResponse
    {
        $this->authorizeOwner($request, $analysis);

        $analysis->load('photos');

        return response()->json(['data' => new AnalysisResource($analysis)]);
    }

    /**
     * Start payment. With Stripe configured (in the admin) returns a Checkout
     * URL; otherwise marks paid immediately (dev) and starts the reveal timer.
     */
    public function checkout(Request $request, Analysis $analysis): JsonResponse
    {
        $this->authorizeOwner($request, $analysis);

        if ($analysis->paid_at) {
            return response()->json(['url' => null, 'message' => 'Already paid.']);
        }

        $frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
        $gateway = StripeGateway::make();

        if ($gateway->ready()) {
            $session = $gateway->createCheckoutSession(
                $analysis->id,
                // Stripe fills {CHECKOUT_SESSION_ID}; the status page confirms it.
                "{$frontend}/status/{$analysis->id}?session_id={CHECKOUT_SESSION_ID}",
                "{$frontend}/pay/{$analysis->id}",
            );

            return response()->json(['url' => $session->url]);
        }

        // ── Dev fallback: no Stripe configured. Mark paid + start reveal timer.
        $this->markPaid($analysis);

        return response()->json([
            'url'       => null,
            'reveal_at' => $analysis->fresh()->reveal_at?->toIso8601String(),
        ]);
    }

    /**
     * Confirm payment when the user returns from Stripe Checkout.
     *
     * This is the synchronous path: we verify the Checkout Session with Stripe
     * and mark the analysis paid. It makes the flow reliable even when the
     * webhook is delayed or (in local dev) can't reach the server. The webhook
     * remains the authoritative/backup path; markPaid() is idempotent.
     */
    public function confirm(Request $request, Analysis $analysis): JsonResponse
    {
        $this->authorizeOwner($request, $analysis);

        if (! $analysis->paid_at) {
            $sessionId = (string) $request->input('session_id');
            $gateway = StripeGateway::make();

            if ($sessionId !== '' && $gateway->ready()) {
                try {
                    $session = $gateway->retrieveSession($sessionId);
                    $belongs = (($session->metadata->analysisId ?? null) === $analysis->id);
                    $paid = in_array($session->payment_status ?? null, ['paid', 'no_payment_required'], true);

                    if ($belongs && $paid) {
                        $this->markPaid($analysis);
                    }
                } catch (\Throwable $e) {
                    // Leave it for the webhook; report current state below.
                }
            }
        }

        $fresh = $analysis->fresh();

        return response()->json([
            'paid'      => (bool) $fresh->paid_at,
            'reveal_at' => $fresh->reveal_at?->toIso8601String(),
        ]);
    }

    /** Shared: flip an analysis to paid/queued, set reveal_at, record payment. */
    public static function markPaid(Analysis $analysis): void
    {
        if ($analysis->paid_at) {
            return;
        }

        $settings = Settings::get();

        $analysis->update([
            'status'    => 'queued',
            'paid_at'   => now(),
            'reveal_at' => Settings::computeRevealAt(),
        ]);

        Payment::create([
            'id'           => (string) Str::uuid(),
            'analysis_id'  => $analysis->id,
            'user_id'      => $analysis->user_id,
            'amount_cents' => (int) $settings->price_cents,
            'currency'     => (string) $settings->currency,
            'status'       => 'paid',
            'created_at'   => now(),
        ]);
    }

    private function authorizeOwner(Request $request, Analysis $analysis): void
    {
        abort_unless($analysis->user_id === $request->user()->id, 403);
    }
}
