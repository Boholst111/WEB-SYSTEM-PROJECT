<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;

class RegisterController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password_hash' => Hash::make($request->password),
                'phone' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'loyalty_tier' => 'bronze',
                'loyalty_credits' => 0.00,
                'total_spent' => 0.00,
                'status' => 'active',
                'preferences' => [],
            ]);

            // Fire the registered event for email verification
            // event(new Registered($user));

            // Create API token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully. Please check your email for verification.',
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
                        'createdAt' => $user->created_at,
                        'updatedAt' => $user->updated_at,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'hash' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->id);

        if (!hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link'
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified'
            ]);
        }

        if ($user->markEmailAsVerified()) {
            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Email verification failed'
        ], 500);
    }

    /**
     * Resend email verification.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent'
        ]);
    }
}