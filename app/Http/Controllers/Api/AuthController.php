<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'name'     => ['nullable', 'string', 'max:120'],
        ]);

        $user = AuthUser::create([
            'id'                 => (string) Str::uuid(),
            'email'              => $data['email'],
            'name'               => $data['name'] ?? null,
            'password'           => $data['password'], // hashed by cast
            'email_confirmed_at' => now(),
            'last_sign_in_at'    => now(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return response()->json([
            'user'  => $this->userPayload($user),
            'token' => $user->createToken('frontend')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = AuthUser::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $user->forceFill(['last_sign_in_at' => now()])->save();

        return response()->json([
            'user'  => $this->userPayload($user),
            'token' => $user->createToken('frontend')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    // ── Google sign-in (OAuth via Socialite, stateless) ───────────────────

    /** Step 1: send the browser to Google. */
    public function googleRedirect()
    {
        $settings = AppSetting::singleton();
        if (! $settings->googleReady()) {
            return redirect($this->frontendUrl('/signin?error=google_disabled'));
        }

        $this->configureGoogle($settings);

        return Socialite::driver('google')->stateless()->redirect();
    }

    /** Step 2: Google returns here; create/find the user, issue a token, bounce to the app. */
    public function googleCallback(Request $request)
    {
        $settings = AppSetting::singleton();
        if (! $settings->googleReady()) {
            return redirect($this->frontendUrl('/signin?error=google_disabled'));
        }

        $this->configureGoogle($settings);

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect($this->frontendUrl('/signin?error=google_failed'));
        }

        $email = $googleUser->getEmail();
        if (! $email) {
            return redirect($this->frontendUrl('/signin?error=google_no_email'));
        }

        $user = AuthUser::where('email', $email)->first();
        if (! $user) {
            $user = AuthUser::create([
                'id'                 => (string) Str::uuid(),
                'email'              => $email,
                'name'               => $googleUser->getName() ?: null,
                // Random password: this account signs in with Google.
                'password'           => Str::random(40), // hashed by cast
                'email_confirmed_at' => now(),
                'last_sign_in_at'    => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } else {
            $user->forceFill(['last_sign_in_at' => now()])->save();
        }

        $token = $user->createToken('google')->plainTextToken;

        // Hand the token to the frontend, which stores it and continues.
        return redirect($this->frontendUrl('/auth/callback?token='.urlencode($token)));
    }

    /** Push the admin-managed Google credentials into Socialite's config. */
    private function configureGoogle(AppSetting $settings): void
    {
        config([
            'services.google.client_id'     => $settings->googleClientId(),
            'services.google.client_secret' => $settings->googleClientSecret(),
            'services.google.redirect'      => url('/api/auth/google/callback'),
        ]);
    }

    /** Absolute frontend URL for a given path. */
    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
        return $base.$path;
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    private function userPayload(AuthUser $user): array
    {
        return [
            'id'    => $user->id,
            'email' => $user->email,
            'name'  => $user->name,
        ];
    }
}
