<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function payments(Request $request): JsonResponse
    {
        $rows = Payment::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Payment $p) => [
                'id'         => $p->id,
                'amount'     => $p->amount_display,
                'status'     => $p->status,
                'created_at' => optional($p->created_at)?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** Delete the account and (via FK cascade) all analyses/photos/payments. */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['ok' => true]);
    }
}
