<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Loyalty System Settings
            [
                'key' => 'loyalty_credits_per_peso',
                'value' => '0.01',
                'type' => 'decimal',
                'group' => 'loyalty',
                'description' => 'Credits earned per peso spent',
                'is_public' => true,
            ],
            [
                'key' => 'loyalty_tier_bronze_threshold',
                'value' => '0',
                'type' => 'decimal',
                'group' => 'loyalty',
                'description' => 'Minimum spending for Bronze tier',
                'is_public' => true,
            ],
            [
                'key' => 'loyalty_tier_silver_threshold',
                'value' => '10000',
                'type' => 'decimal',
                'group' => 'loyalty',
                'description' => 'Minimum spending for Silver tier',
                'is_public' => true,
            ],
            [
                'key' => 'loyalty_tier_gold_threshold',
                'value' => '25000',
                'type' => 'decimal',
                'group' => 'loyalty',
                'description' => 'Minimum spending for Gold tier',
                'is_public' => true,
            ],
            [
                'key' => 'loyalty_tier_platinum_threshold',
                'value' => '50000',
                'type' => 'decimal',
                'group' => 'loyalty',
                'description' => 'Minimum spending for Platinum tier',
                'is_public' => true,
            ],
            [
                'key' => 'loyalty_credits_expiry_months',
                'value' => '12',
                'type' => 'integer',
                'group' => 'loyalty',
                'description' => 'Months before credits expire',
                'is_public' => true,
            ],
            
            // Pre-order Settings
            [
                'key' => 'preorder_deposit_percentage',
                'value' => '30',
                'type' => 'integer',
                'group' => 'preorder',
                'description' => 'Default deposit percentage for pre-orders',
                'is_public' => true,
            ],
            [
                'key' => 'preorder_payment_reminder_days',
                'value' => '7',
                'type' => 'integer',
                'group' => 'preorder',
                'description' => 'Days before due date to send payment reminder',
                'is_public' => false,
            ],
            
            // Shipping Settings
            [
                'key' => 'free_shipping_threshold',
                'value' => '1500',
                'type' => 'decimal',
                'group' => 'shipping',
                'description' => 'Minimum order amount for free shipping',
                'is_public' => true,
            ],
            [
                'key' => 'standard_shipping_fee',
                'value' => '150',
                'type' => 'decimal',
                'group' => 'shipping',
                'description' => 'Standard shipping fee',
                'is_public' => true,
            ],
            
            // Payment Settings
            [
                'key' => 'payment_gcash_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'payment',
                'description' => 'Enable GCash payments',
                'is_public' => true,
            ],
            [
                'key' => 'payment_maya_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'payment',
                'description' => 'Enable Maya payments',
                'is_public' => true,
            ],
            [
                'key' => 'payment_bank_transfer_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'payment',
                'description' => 'Enable bank transfer payments',
                'is_public' => true,
            ],
            
            // General Settings
            [
                'key' => 'site_name',
                'value' => 'Diecast Empire',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Site name',
                'is_public' => true,
            ],
            [
                'key' => 'site_currency',
                'value' => 'PHP',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Default currency',
                'is_public' => true,
            ],
            [
                'key' => 'low_stock_threshold',
                'value' => '5',
                'type' => 'integer',
                'group' => 'inventory',
                'description' => 'Low stock alert threshold',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}