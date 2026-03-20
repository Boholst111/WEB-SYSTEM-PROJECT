<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Mail\OrderConfirmed;
use App\Mail\OrderShipped;
use App\Mail\PreOrderArrival;
use App\Mail\PreOrderPaymentReminder;
use App\Mail\LoyaltyTierAdvancement;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class NotificationService
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    /**
     * Send order status update notification to customer.
     */
    public function sendOrderStatusUpdate(Order $order, string $oldStatus, string $newStatus): void
    {
        try {
            $user = $order->user;
            
            // Email notification
            $this->sendOrderStatusEmail($order, $oldStatus, $newStatus);
            
            // SMS notification for important status changes
            if ($this->shouldSendSMSForStatus($newStatus)) {
                $this->sendOrderStatusSMS($order, $newStatus);
            }
            
            Log::info('Order status notification sent', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send order status notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send payment exception notification to customer.
     */
    public function sendPaymentExceptionNotification(Order $order, string $action, array $result): void
    {
        try {
            $user = $order->user;
            
            $emailData = [
                'order' => $order,
                'action' => $action,
                'result' => $result,
                'user' => $user
            ];
            
            // Send appropriate email based on action
            switch ($action) {
                case 'retry_payment':
                    $this->sendEmail($user->email, 'Payment Retry - Order #' . $order->order_number, 'emails.payment.retry', $emailData);
                    break;
                    
                case 'mark_paid':
                    $this->sendEmail($user->email, 'Payment Confirmed - Order #' . $order->order_number, 'emails.payment.confirmed', $emailData);
                    break;
                    
                case 'refund':
                    $this->sendEmail($user->email, 'Refund Processed - Order #' . $order->order_number, 'emails.payment.refund', $emailData);
                    break;
                    
                case 'cancel':
                    $this->sendEmail($user->email, 'Order Cancelled - Order #' . $order->order_number, 'emails.order.cancelled', $emailData);
                    break;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send payment exception notification', [
                'order_id' => $order->id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send inventory exception notification to customer.
     */
    public function sendInventoryExceptionNotification(Order $order, string $action, array $result): void
    {
        try {
            $user = $order->user;
            
            $emailData = [
                'order' => $order,
                'action' => $action,
                'result' => $result,
                'user' => $user
            ];
            
            // Send appropriate email based on action
            switch ($action) {
                case 'substitute_product':
                    $this->sendEmail($user->email, 'Product Substitution - Order #' . $order->order_number, 'emails.inventory.substitution', $emailData);
                    break;
                    
                case 'partial_fulfillment':
                    $this->sendEmail($user->email, 'Partial Shipment - Order #' . $order->order_number, 'emails.inventory.partial', $emailData);
                    break;
                    
                case 'cancel_items':
                    $this->sendEmail($user->email, 'Items Cancelled - Order #' . $order->order_number, 'emails.inventory.cancelled', $emailData);
                    break;
                    
                case 'wait_restock':
                    $this->sendEmail($user->email, 'Waiting for Restock - Order #' . $order->order_number, 'emails.inventory.restock', $emailData);
                    break;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send inventory exception notification', [
                'order_id' => $order->id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send shipping notification with tracking information.
     */
    public function sendShippingNotification(Order $order): void
    {
        try {
            $user = $order->user;
            
            $emailData = [
                'order' => $order,
                'user' => $user,
                'tracking_number' => $order->tracking_number,
                'courier_service' => $order->courier_service,
                'estimated_delivery' => $order->estimated_delivery
            ];
            
            $this->sendEmail(
                $user->email,
                'Your Order Has Shipped - Order #' . $order->order_number,
                'emails.order.shipped',
                $emailData
            );
            
            // SMS notification with tracking number
            if ($user->phone && $order->tracking_number) {
                $message = "Your order #{$order->order_number} has shipped! Track it with: {$order->tracking_number}";
                $this->sendSMS($user->phone, $message);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send shipping notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send delivery confirmation notification.
     */
    public function sendDeliveryNotification(Order $order): void
    {
        try {
            $user = $order->user;
            
            $emailData = [
                'order' => $order,
                'user' => $user,
                'delivered_at' => $order->delivered_at
            ];
            
            $this->sendEmail(
                $user->email,
                'Order Delivered - Order #' . $order->order_number,
                'emails.order.delivered',
                $emailData
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to send delivery notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send bulk notification to multiple customers.
     */
    public function sendBulkNotification(array $userIds, string $subject, string $message, array $options = []): array
    {
        $results = [];
        $successful = 0;
        $failed = 0;

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            try {
                if ($options['email'] ?? true) {
                    $this->sendEmail($user->email, $subject, 'emails.bulk.notification', [
                        'user' => $user,
                        'subject' => $subject,
                        'message' => $message
                    ]);
                }

                if (($options['sms'] ?? false) && $user->phone) {
                    $this->sendSMS($user->phone, $message);
                }

                $successful++;
                $results[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'success' => true
                ];

            } catch (\Exception $e) {
                $failed++;
                $results[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'summary' => [
                'total' => count($users),
                'successful' => $successful,
                'failed' => $failed
            ],
            'results' => $results
        ];
    }

    /**
     * Send order status email notification.
     */
    private function sendOrderStatusEmail(Order $order, string $oldStatus, string $newStatus): void
    {
        $user = $order->user;

        // Check user preferences
        if (!$this->shouldSendEmail($user, 'order_updates')) {
            return;
        }

        try {
            $mailable = match ($newStatus) {
                'confirmed' => new OrderConfirmed($order),
                'shipped' => new OrderShipped($order),
                default => null
            };

            if ($mailable) {
                Mail::to($user->email)->queue($mailable);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send order status email', [
                'order_id' => $order->id,
                'status' => $newStatus,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send order status SMS notification.
     */
    private function sendOrderStatusSMS(Order $order, string $newStatus): void
    {
        $user = $order->user;
        
        if (!$user->phone || !$this->shouldSendSMS($user, 'order_updates')) {
            return;
        }

        $message = match ($newStatus) {
            'confirmed' => "Your order #{$order->order_number} has been confirmed and is being prepared.",
            'shipped' => "Your order #{$order->order_number} has shipped! Tracking: {$order->tracking_number}",
            'delivered' => "Your order #{$order->order_number} has been delivered. Thank you for shopping with us!",
            'cancelled' => "Your order #{$order->order_number} has been cancelled. If you have questions, please contact us.",
            default => "Your order #{$order->order_number} status has been updated to: {$order->status_label}"
        };

        $this->smsService->send($user->phone, $message);
    }

    /**
     * Send email using configured mail service.
     */
    private function sendEmail(string $to, string $subject, string $template, array $data): void
    {
        try {
            Mail::send($template, $data, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
            
            Log::info('Email notification sent', [
                'to' => $to,
                'subject' => $subject,
                'template' => $template
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send SMS using SMS service.
     */
    private function sendSMS(string $phone, string $message): void
    {
        $this->smsService->send($phone, $message);
    }

    /**
     * Check if user preferences allow email notifications.
     */
    private function shouldSendEmail(User $user, string $type): bool
    {
        if (!config('notifications.email.enabled', true)) {
            return false;
        }

        $preferences = $user->preferences ?? [];
        
        return match($type) {
            'order_updates' => $preferences['allow_order_updates'] ?? true,
            'preorder_notifications' => $preferences['allow_preorder_notifications'] ?? true,
            'loyalty_notifications' => $preferences['allow_loyalty_notifications'] ?? true,
            'marketing' => $preferences['allow_email_marketing'] ?? false,
            default => true
        };
    }

    /**
     * Check if user preferences allow SMS notifications.
     */
    private function shouldSendSMS(User $user, string $type): bool
    {
        if (!config('notifications.sms.enabled', true)) {
            return false;
        }

        $preferences = $user->preferences ?? [];
        
        return match($type) {
            'order_updates' => $preferences['allow_order_updates'] ?? true,
            'preorder_notifications' => $preferences['allow_preorder_notifications'] ?? true,
            'marketing' => $preferences['allow_sms_marketing'] ?? false,
            default => true
        };
    }

    /**
     * Determine if SMS should be sent for status change.
     */
    private function shouldSendSMSForStatus(string $status): bool
    {
        return in_array($status, ['shipped', 'delivered', 'cancelled']);
    }

    /**
     * Send low stock alert to administrators.
     */
    public function sendLowStockAlert($product): void
    {
        try {
            Log::info('Low stock alert', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'current_stock' => $product->stock_quantity
            ]);
            
            // In a real implementation, this would send email to admin users
            // or integrate with admin notification systems
            
        } catch (\Exception $e) {
            Log::error('Failed to send low stock alert', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send pre-order arrival notification to customer.
     */
    public function sendPreOrderArrivalNotification($preorder): void
    {
        try {
            $user = $preorder->user;
            $product = $preorder->product;
            
            // Email notification
            if ($this->shouldSendEmail($user, 'preorder_notifications')) {
                Mail::to($user->email)->queue(new PreOrderArrival($preorder));
            }
            
            // SMS notification
            if ($user->phone && $this->shouldSendSMS($user, 'preorder_notifications')) {
                $message = "Great news! Your pre-order #{$preorder->preorder_number} for {$product->name} has arrived and is ready for final payment.";
                $this->smsService->send($user->phone, $message);
            }
            
            Log::info('Pre-order arrival notification sent', [
                'preorder_id' => $preorder->id,
                'user_id' => $user->id,
                'product_id' => $product->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send pre-order arrival notification', [
                'preorder_id' => $preorder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send pre-order payment reminder notification.
     */
    public function sendPreOrderPaymentReminder($preorder): void
    {
        try {
            $user = $preorder->user;
            
            // Email notification
            if ($this->shouldSendEmail($user, 'preorder_notifications')) {
                Mail::to($user->email)->queue(new PreOrderPaymentReminder($preorder));
            }
            
            // SMS notification
            if ($user->phone && $this->shouldSendSMS($user, 'preorder_notifications')) {
                $daysLeft = $preorder->days_until_due ?? 0;
                $message = "Reminder: Your pre-order #{$preorder->preorder_number} payment is due in {$daysLeft} days. Balance: ₱" . number_format($preorder->remaining_amount, 2);
                $this->smsService->send($user->phone, $message);
            }
            
            Log::info('Pre-order payment reminder sent', [
                'preorder_id' => $preorder->id,
                'user_id' => $user->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send pre-order payment reminder', [
                'preorder_id' => $preorder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send loyalty tier advancement notification.
     */
    public function sendLoyaltyTierAdvancement(User $user, string $oldTier, string $newTier): void
    {
        try {
            // Email notification
            if ($this->shouldSendEmail($user, 'loyalty_notifications')) {
                Mail::to($user->email)->queue(new LoyaltyTierAdvancement($user, $oldTier, $newTier));
            }
            
            Log::info('Loyalty tier advancement notification sent', [
                'user_id' => $user->id,
                'old_tier' => $oldTier,
                'new_tier' => $newTier
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send loyalty tier advancement notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}