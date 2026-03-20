<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NotificationTemplate;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Order Confirmed',
                'type' => 'order_confirmed',
                'subject' => 'Order Confirmed - Order #{{order_number}}',
                'email_body' => 'Your order #{{order_number}} has been confirmed and is being prepared for shipment.',
                'sms_body' => 'Your order #{{order_number}} has been confirmed.',
                'variables' => ['order_number', 'customer_name'],
                'is_active' => true,
            ],
            [
                'name' => 'Order Shipped',
                'type' => 'order_shipped',
                'subject' => 'Your Order Has Shipped - Order #{{order_number}}',
                'email_body' => 'Great news! Your order #{{order_number}} has shipped. Tracking: {{tracking_number}}',
                'sms_body' => 'Your order #{{order_number}} has shipped! Track: {{tracking_number}}',
                'variables' => ['order_number', 'tracking_number', 'customer_name'],
                'is_active' => true,
            ],
            [
                'name' => 'Pre-order Arrival',
                'type' => 'preorder_arrival',
                'subject' => 'Your Pre-order Has Arrived - {{product_name}}',
                'email_body' => 'Exciting news! Your pre-order for {{product_name}} has arrived. Please complete payment to secure your item.',
                'sms_body' => 'Your pre-order {{product_name}} has arrived! Complete payment now.',
                'variables' => ['product_name', 'preorder_number', 'remaining_amount'],
                'is_active' => true,
            ],
            [
                'name' => 'Pre-order Payment Reminder',
                'type' => 'preorder_payment_reminder',
                'subject' => 'Payment Reminder - Pre-order #{{preorder_number}}',
                'email_body' => 'Reminder: Your pre-order payment of ₱{{remaining_amount}} is due in {{days_left}} days.',
                'sms_body' => 'Reminder: Pre-order payment due in {{days_left}} days. Balance: ₱{{remaining_amount}}',
                'variables' => ['preorder_number', 'remaining_amount', 'days_left'],
                'is_active' => true,
            ],
            [
                'name' => 'Loyalty Tier Advancement',
                'type' => 'loyalty_tier_advancement',
                'subject' => 'Congratulations! You\'ve Advanced to {{new_tier}} Tier',
                'email_body' => 'Congratulations {{customer_name}}! You\'ve been upgraded to {{new_tier}} tier with exclusive benefits.',
                'sms_body' => null,
                'variables' => ['customer_name', 'new_tier', 'old_tier'],
                'is_active' => true,
            ],
            [
                'name' => 'Low Stock Alert',
                'type' => 'low_stock_alert',
                'subject' => 'Low Stock Alert - {{product_name}}',
                'email_body' => 'Product {{product_name}} (SKU: {{sku}}) is running low. Current stock: {{stock_quantity}}',
                'sms_body' => null,
                'variables' => ['product_name', 'sku', 'stock_quantity'],
                'is_active' => true,
            ],
            [
                'name' => 'Security Alert',
                'type' => 'security_alert',
                'subject' => 'Security Alert - {{alert_type}}',
                'email_body' => 'We detected {{alert_type}} on your account. If this wasn\'t you, please contact us immediately.',
                'sms_body' => 'Security alert: {{alert_type}} detected on your account.',
                'variables' => ['alert_type', 'timestamp', 'ip_address'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['type' => $template['type']],
                $template
            );
        }
    }
}
