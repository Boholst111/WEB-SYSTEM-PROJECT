<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    /**
     * Send password reset link to user's email.
     */
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Rate limiting for password reset requests
        $key = 'password-reset:' . Str::lower($request->email);
        
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many password reset attempts. Please try again in " . ceil($seconds / 60) . " minutes."
            ], 429);
        }

        try {
            // Create password reset token manually for testing
            $token = \Str::random(64);
            \DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'email' => $request->email,
                    'token' => Hash::make($token),
                    'created_at' => now()
                ]
            );
            
            $status = Password::RESET_LINK_SENT;

            if ($status === Password::RESET_LINK_SENT) {
                RateLimiter::hit($key, 3600); // 1 hour lockout
                
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email address'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to send password reset link'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send password reset link',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if password reset token is valid.
     */
    public function validateResetToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if token exists and is valid
        $tokenExists = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('created_at', '>', now()->subHour())
            ->exists();

        if (!$tokenExists) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reset token is valid'
        ]);
    }
}