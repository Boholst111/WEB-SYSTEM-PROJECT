<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get notification statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'day');
        
        $stats = NotificationLog::getStats($period);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get notification logs with filters.
     */
    public function logs(Request $request): JsonResponse
    {
        $query = NotificationLog::query()->with('user');

        // Apply filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Send bulk notification to users.
     */
    public function sendBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'channels' => 'required|array',
            'channels.*' => 'in:email,sms',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userIds = $request->input('user_ids');
        $subject = $request->input('subject');
        $message = $request->input('message');
        $channels = $request->input('channels');

        $options = [
            'email' => in_array('email', $channels),
            'sms' => in_array('sms', $channels),
        ];

        $results = $this->notificationService->sendBulkNotification(
            $userIds,
            $subject,
            $message,
            $options
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk notification sent',
            'data' => $results
        ]);
    }

    /**
     * List all notification templates.
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = NotificationTemplate::orderBy('name')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Get a specific template.
     */
    public function getTemplate(int $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    /**
     * Create a new notification template.
     */
    public function createTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100|unique:notification_templates,type',
            'subject' => 'nullable|string|max:255',
            'email_body' => 'nullable|string',
            'sms_body' => 'nullable|string|max:160',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $template = NotificationTemplate::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'data' => $template
        ], 201);
    }

    /**
     * Update a notification template.
     */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'subject' => 'nullable|string|max:255',
            'email_body' => 'nullable|string',
            'sms_body' => 'nullable|string|max:160',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $template->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => $template
        ]);
    }

    /**
     * Delete a notification template.
     */
    public function deleteTemplate(int $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
    }

    /**
     * Retry failed notifications.
     */
    public function retryFailed(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 100);
        $failedNotifications = NotificationLog::getFailedForRetry($limit);

        $retried = 0;
        foreach ($failedNotifications as $notification) {
            // Attempt to resend based on channel
            // This is a simplified version - in production, you'd want more sophisticated retry logic
            $notification->update(['status' => 'pending']);
            $retried++;
        }

        return response()->json([
            'success' => true,
            'message' => "Queued {$retried} notifications for retry"
        ]);
    }
}
