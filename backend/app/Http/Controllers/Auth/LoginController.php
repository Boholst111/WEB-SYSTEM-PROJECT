<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * Login user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Rate limiting
        $key = Str::lower($request->email) . '|' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$seconds} seconds."
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            RateLimiter::hit($key, 300); // 5 minutes lockout
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if user account is active
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is suspended or inactive'
            ], 403);
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        // Revoke existing tokens if not remember me
        if (!$request->remember) {
            $user->tokens()->delete();
        }

        // Create new token
        $tokenName = 'auth_token_' . now()->timestamp;
        $token = $user->createToken($tokenName)->plainTextToken;

        // Update last login
        $user->update(['last_login_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'firstName' => $user->first_name,
                        'lastName' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'dateOfBirth' => $user->date_of_birth,
                        'loyaltyTier' => $user->loyalty_tier,
                        'loyaltyCredits' => (float) $user->loyalty_credits,
                        'totalSpent' => (float) $user->total_spent,
                        'emailVerifiedAt' => $user->email_verified_at,
                        'status' => $user->status,
                        'role' => $user->role ?? 'user',
                        'preferences' => $user->preferences,
                        'createdAt' => $user->created_at,
                        'updatedAt' => $user->updated_at,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ]);
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Logout from all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            // Revoke all tokens
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all devices successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Refresh token.
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke current token
            $request->user()->currentAccessToken()->delete();
            
            // Create new token
            $tokenName = 'auth_token_' . now()->timestamp;
            $token = $user->createToken($tokenName)->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'dateOfBirth' => $user->date_of_birth,
                    'loyaltyTier' => $user->loyalty_tier,
                    'loyaltyCredits' => (float) $user->loyalty_credits,
                    'totalSpent' => (float) $user->total_spent,
                    'emailVerifiedAt' => $user->email_verified_at,
                    'phoneVerifiedAt' => $user->phone_verified_at,
                    'status' => $user->status,
                    'role' => $user->role ?? 'user',
                    'preferences' => $user->preferences,
                    'createdAt' => $user->created_at,
                    'updatedAt' => $user->updated_at,
                ]
            ]
        ]);
    }
}