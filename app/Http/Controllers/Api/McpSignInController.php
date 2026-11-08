<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class McpSignInController extends Controller
{
    public function __invoke(Request $request)
    {
        $input = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:1024']);
        // Per-email limits also apply when multiple users share a tunnel proxy IP.
        $key = 'mcp-sign-in:'.hash('sha256', mb_strtolower($input['email']));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many sign-in attempts.'], 429)->header('Cache-Control', 'no-store');
        }
        RateLimiter::hit($key, 60);
        $user = User::where('email', $input['email'])->first();
        // A fixed dummy hash keeps unknown-email requests on the password-hash path.
        $hash = $user?->getAuthPassword() ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $passwordMatches = Hash::check($input['password'], $hash);
        if (! $user || ! $passwordMatches || ! $user->is_active) {
            return response()->json(['message' => 'Invalid credentials.'], 401)->header('Cache-Control', 'no-store');
        }
        RateLimiter::clear($key);
        $expiresAt = now()->addDays(7);
        $token = $user->createToken('syncora-mcp', ['mcp:read', 'mcp:media:write'], $expiresAt)->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer', 'access_token' => $token, 'expires_at' => $expiresAt->toIso8601String(),
        ])->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }
}
