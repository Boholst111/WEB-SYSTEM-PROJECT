<?php

namespace Database\Factories;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Models\Order;
use App\Models\PreOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoyaltyTransactionFactory extends Factory
{
    protected $model = LoyaltyTransaction::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1, 500);
        $balanceBefore = fake()->randomFloat(2, 0, 1000);
        $balanceAfter = $balanceBefore + $amount;
        
        return [
            'user_id' => User::factory(),
            'order_id' => null, // Don't create by default
            'preorder_id' => null, // Don't create by default
            'transaction_type' => fake()->randomElement([
                LoyaltyTransaction::TYPE_EARNED,
                LoyaltyTransaction::TYPE_REDEEMED,
                LoyaltyTransaction::TYPE_BONUS,
                LoyaltyTransaction::TYPE_ADJUSTMENT,
            ]),
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => fake()->sentence(),
            'reference_id' => fake()->optional()->regexify('[A-Z]{3}[0-9]{6}'),
            'expires_at' => fake()->optional()->dateTimeBetween('+6 months', '+2 years'),
            'is_expired' => fake()->boolean(10), // 10% chance
            'metadata' => fake()->optional()->randomElement([
                ['campaign' => 'new_year', 'bonus_type' => 'signup'],
                ['tier_upgrade' => 'gold', 'multiplier' => 1.5],
                null,
            ]),
        ];
    }

    public function earned(): static
    {
        return $this->state(function (array $attributes) {
            $amount = fake()->randomFloat(2, 10, 200);
            $balanceBefore = $attributes['balance_before'] ?? fake()->randomFloat(2, 0, 1000);
            
            return [
                'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
                'amount' => $amount,
                'balance_after' => $balanceBefore + $amount,
                'description' => 'Credits earned from purchase',
                'expires_at' => fake()->dateTimeBetween('+6 months', '+1 year'),
                'is_expired' => false,
            ];
        });
    }

    public function redeemed(): static
    {
        return $this->state(function (array $attributes) {
            $amount = fake()->randomFloat(2, 5, 100);
            $balanceBefore = $attributes['balance_before'] ?? fake()->randomFloat(2, $amount, 1000);
            
            return [
                'transaction_type' => LoyaltyTransaction::TYPE_REDEEMED,
                'amount' => -$amount, // Negative for redemption
                'balance_after' => $balanceBefore - $amount,
                'description' => 'Credits redeemed for discount',
                'expires_at' => null,
            ];
        });
    }

    public function bonus(): static
    {
        return $this->state(function (array $attributes) {
            $amount = fake()->randomFloat(2, 25, 500);
            $balanceBefore = $attributes['balance_before'] ?? fake()->randomFloat(2, 0, 1000);
            
            return [
                'transaction_type' => LoyaltyTransaction::TYPE_BONUS,
                'amount' => $amount,
                'balance_after' => $balanceBefore + $amount,
                'description' => 'Bonus credits awarded',
                'expires_at' => fake()->dateTimeBetween('+6 months', '+1 year'),
                'is_expired' => false,
                'metadata' => [
                    'campaign' => fake()->randomElement(['signup', 'referral', 'birthday', 'holiday']),
                    'bonus_type' => 'promotional',
                ],
            ];
        });
    }

    public function adjustment(): static
    {
        return $this->state(function (array $attributes) {
            $amount = fake()->randomFloat(2, -100, 100);
            $balanceBefore = $attributes['balance_before'] ?? fake()->randomFloat(2, 100, 1000);
            
            return [
                'transaction_type' => LoyaltyTransaction::TYPE_ADJUSTMENT,
                'amount' => $amount,
                'balance_after' => $balanceBefore + $amount,
                'description' => 'Manual adjustment by admin',
                'expires_at' => null,
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(function (array $attributes) {
            $amount = fake()->randomFloat(2, 10, 200);
            $balanceBefore = $attributes['balance_before'] ?? fake()->randomFloat(2, $amount, 1000);
            
            return [
                'transaction_type' => LoyaltyTransaction::TYPE_EXPIRED,
                'amount' => -$amount, // Negative for expiration
                'balance_after' => $balanceBefore - $amount,
                'description' => 'Credits expired',
                'expires_at' => null,
                'is_expired' => true,
            ];
        });
    }

    public function notExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_expired' => false,
            'expires_at' => fake()->dateTimeBetween('+1 month', '+1 year'),
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => LoyaltyTransaction::TYPE_EARNED,
            'is_expired' => false,
            'expires_at' => fake()->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'preorder_id' => null,
        ]);
    }

    public function forPreOrder(PreOrder $preorder): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => null,
            'preorder_id' => $preorder->id,
        ]);
    }
}