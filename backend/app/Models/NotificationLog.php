<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'recipient',
        'subject',
        'message',
        'status',
        'error_message',
        'sent_at',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that received the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get failed notifications for retry.
     */
    public static function getFailedForRetry(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('status', 'failed')
            ->where('created_at', '>', now()->subDays(7))
            ->limit($limit)
            ->get();
    }

    /**
     * Get notification statistics.
     */
    public static function getStats(string $period = 'day'): array
    {
        $startDate = match($period) {
            'hour' => now()->subHour(),
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay()
        };

        $total = self::where('created_at', '>=', $startDate)->count();
        $sent = self::where('created_at', '>=', $startDate)
            ->where('status', 'sent')
            ->count();
        $failed = self::where('created_at', '>=', $startDate)
            ->where('status', 'failed')
            ->count();

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
        ];
    }
}
