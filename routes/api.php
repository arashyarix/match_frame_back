<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// ── Public ───────────────────────────────────────────────────────────────
Route::get('/config', [ConfigController::class, 'show']);          // price, reveal window, publishable key
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Google sign-in (OAuth). The browser is sent to /redirect, Google returns to
// /callback, which bounces back to the frontend with a token.
Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
// Stripe calls this directly; verified by the stored webhook secret.
Route::post('/stripe/webhook', [WebhookController::class, 'handle']);

// ── Authenticated (Sanctum bearer token) ──────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/analyses', [AnalysisController::class, 'index']);
    Route::post('/analyses', [AnalysisController::class, 'store']);          // create + upload photos
    Route::get('/analyses/{analysis}', [AnalysisController::class, 'show']); // report gated by reveal_at
    Route::post('/analyses/{analysis}/checkout', [AnalysisController::class, 'checkout']);
    Route::post('/analyses/{analysis}/confirm', [AnalysisController::class, 'confirm']); // verify on return from Stripe

    Route::get('/payments', [AccountController::class, 'payments']);
    Route::delete('/account', [AccountController::class, 'destroy']);
});
