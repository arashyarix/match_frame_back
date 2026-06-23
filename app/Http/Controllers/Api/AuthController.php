<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
