<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NotificationPreferenceController extends Controller
{
    /**
     * Get user's notification preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $preferences = NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            NotificationPreference::defaults()
        );

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    /**
     * Update user's notification preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'allow_email_marketing' => 'sometimes|boolean',
            'allow_sms_marketing' => 'sometimes|boolean',
            'allow_order_updates' => 'sometimes|boolean',
            'allow_preorder_notifications' => 'sometimes|boolean',
            'allow_loyalty_notifications' => 'sometimes|boolean',
            'allow_security_alerts' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        $preferences = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validator->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
            'data' => $preferences
        ]);
    }

    /**
     * Reset preferences to defaults.
     */
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $preferences = NotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            NotificationPreference::defaults()
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences reset to defaults',
            'data' => $preferences
        ]);
    }
}
