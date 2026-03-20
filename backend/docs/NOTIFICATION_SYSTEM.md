# Notification System Documentation

## Overview

The Diecast Empire notification system provides comprehensive email and SMS notification capabilities for order updates, pre-order management, loyalty program communications, and administrative alerts.

## Features

### Email Notifications
- Order confirmation and status updates
- Pre-order arrival and payment reminders
- Loyalty tier advancement notifications
- Marketing and promotional campaigns
- Customizable email templates with Blade templating

### SMS Notifications
- Order status updates (shipped, delivered, cancelled)
- Pre-order payment reminders
- Security alerts and verification codes
- Support for Philippine SMS gateways (Semaphore, Itexmo)

### User Preferences
- Granular control over notification types
- Separate preferences for email and SMS
- Marketing opt-in/opt-out management
- Per-user notification settings

### Admin Dashboard
- Notification statistics and analytics
- Bulk notification sending
- Template management system
- Notification log tracking
- Failed notification retry mechanism

## Configuration

### Environment Variables

```env
# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS="hello@diecastempire.com"
MAIL_FROM_NAME="Diecast Empire"

# Notification Settings
NOTIFICATIONS_ENABLED=true
EMAIL_NOTIFICATIONS_ENABLED=true
SMS_NOTIFICATIONS_ENABLED=true

# SMS Provider (Semaphore)
SMS_PROVIDER=semaphore
SEMAPHORE_API_KEY=your_api_key_here
SEMAPHORE_SENDER_NAME=DiecastEmp

# Alternative SMS Provider (Itexmo)
ITEXMO_API_CODE=your_api_code
ITEXMO_PASSWORD=your_password
```

### Configuration Files

- `config/mail.php` - Email service configuration
- `config/notifications.php` - Notification system settings

## Usage

### Sending Notifications

#### Order Status Updates

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);
$notificationService->sendOrderStatusUpdate($order, 'pending', 'confirmed');
```

#### Pre-order Notifications

```php
// Arrival notification
$notificationService->sendPreOrderArrivalNotification($preorder);

// Payment reminder
$notificationService->sendPreOrderPaymentReminder($preorder);
```

#### Loyalty Notifications

```php
$notificationService->sendLoyaltyTierAdvancement($user, 'bronze', 'silver');
```

#### Bulk Notifications

```php
$userIds = [1, 2, 3, 4, 5];
$results = $notificationService->sendBulkNotification(
    $userIds,
    'Special Promotion',
    'Check out our latest arrivals!',
    ['email' => true, 'sms' => false]
);
```

### User Preferences API

#### Get Preferences
```
GET /api/notifications/preferences
```

#### Update Preferences
```
PUT /api/notifications/preferences
{
    "allow_email_marketing": false,
    "allow_sms_marketing": false,
    "allow_order_updates": true,
    "allow_preorder_notifications": true,
    "allow_loyalty_notifications": true
}
```

#### Reset to Defaults
```
POST /api/notifications/preferences/reset
```

### Admin API Endpoints

#### Get Statistics
```
GET /api/admin/notifications/stats?period=day
```

#### View Notification Logs
```
GET /api/admin/notifications/logs?status=failed&date_from=2024-01-01
```

#### Send Bulk Notification
```
POST /api/admin/notifications/send-bulk
{
    "user_ids": [1, 2, 3],
    "subject": "New Product Launch",
    "message": "Check out our latest collection!",
    "channels": ["email", "sms"]
}
```

#### Manage Templates
```
GET /api/admin/notifications/templates
POST /api/admin/notifications/templates
PUT /api/admin/notifications/templates/{id}
DELETE /api/admin/notifications/templates/{id}
```

## Email Templates

Email templates are located in `resources/views/emails/` and use Blade templating:

- `emails/layout.blade.php` - Base layout
- `emails/order/confirmed.blade.php` - Order confirmation
- `emails/order/shipped.blade.php` - Shipping notification
- `emails/preorder/arrival.blade.php` - Pre-order arrival
- `emails/preorder/payment_reminder.blade.php` - Payment reminder
- `emails/loyalty/tier_advancement.blade.php` - Tier upgrade
- `emails/bulk/notification.blade.php` - Bulk notifications

### Creating Custom Templates

1. Create a new Blade template in `resources/views/emails/`
2. Extend the base layout: `@extends('emails.layout')`
3. Define content section: `@section('content')`
4. Create a Mailable class in `app/Mail/`
5. Register in NotificationService

## SMS Integration

### Supported Providers

#### Semaphore (Default)
- API URL: https://api.semaphore.co/api/v4/messages
- Sender name limit: 11 characters
- Message length: 160 characters per SMS

#### Itexmo (Alternative)
- API URL: https://www.itexmo.com/php_api/api.php
- Supports Philippine mobile numbers

### Phone Number Format

The system automatically formats Philippine phone numbers:
- Input: `09171234567` → Output: `639171234567`
- Input: `9171234567` → Output: `639171234567`
- Input: `+639171234567` → Output: `639171234567`

### Rate Limiting

- Email: 10 per user per hour
- SMS: 5 per user per hour
- Bulk email batch size: 100
- Bulk SMS batch size: 50

## Database Schema

### notification_preferences
- User notification settings
- Per-user opt-in/opt-out controls

### notification_templates
- Customizable notification templates
- Support for variables and placeholders

### notification_logs
- Complete audit trail
- Delivery status tracking
- Error logging for failed notifications

## Testing

Run notification system tests:

```bash
php artisan test --filter=NotificationSystemTest
```

Test coverage includes:
- Email delivery
- SMS sending
- User preferences
- Template rendering
- Bulk notifications
- Error handling
- Rate limiting

## Monitoring

### Notification Statistics

Track notification performance:
- Total sent
- Success rate
- Failed notifications
- Channel breakdown (email/SMS)

### Failed Notification Retry

```php
POST /api/admin/notifications/retry-failed
```

Automatically retries failed notifications from the past 7 days.

## Best Practices

1. **Always check user preferences** before sending notifications
2. **Use queues** for email sending to avoid blocking requests
3. **Log all notifications** for audit trail and debugging
4. **Implement rate limiting** to prevent spam
5. **Test templates** thoroughly before deployment
6. **Monitor delivery rates** and investigate failures
7. **Respect opt-out requests** immediately
8. **Keep SMS messages concise** (under 160 characters)
9. **Use meaningful subject lines** for emails
10. **Provide unsubscribe links** in marketing emails

## Troubleshooting

### Emails Not Sending

1. Check mail configuration in `.env`
2. Verify queue workers are running
3. Check notification logs for errors
4. Ensure user preferences allow emails

### SMS Not Sending

1. Verify SMS provider credentials
2. Check phone number format
3. Verify rate limits not exceeded
4. Check SMS provider API status

### Template Rendering Issues

1. Verify all required variables are provided
2. Check Blade syntax in templates
3. Test template rendering in isolation
4. Review error logs for details

## Security Considerations

- Never expose SMS API keys in client-side code
- Validate all user input before sending notifications
- Implement rate limiting to prevent abuse
- Log all notification activities for audit
- Encrypt sensitive data in notification logs
- Respect user privacy and data protection laws

## Future Enhancements

- Push notifications for mobile apps
- In-app notification center
- Notification scheduling
- A/B testing for email campaigns
- Advanced analytics and reporting
- Multi-language support
- Rich media in emails (images, videos)
- SMS delivery reports
- Webhook support for external integrations
