<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'allow_email_marketing',
        'allow_sms_marketing',
        'allow_order_updates',
        'allow_preorder_notifications',
        'allow_loyalty_notifications',
        'allow_security_alerts',
    ];

    protected $casts = [
        'allow_email_marketing' => 'boolean',
        'allow_sms_marketing' => 'boolean',
        'allow_order_updates' => 'boolean',
        'allow_preorder_notifications' => 'boolean',
        'allow_loyalty_notifications' => 'boolean',
        'allow_security_alerts' => 'boolean',
    ];

    /**
     * Get the user that owns the notification preferences.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get default notification preferences.
     */
    public static function defaults(): array
    {
        return [
            'allow_email_marketing' => false,
            'allow_sms_marketing' => false,
            'allow_order_updates' => true,
            'allow_preorder_notifications' => true,
            'allow_loyalty_notifications' => true,
            'allow_security_alerts' => true,
        ];
    }
}
