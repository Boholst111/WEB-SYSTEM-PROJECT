<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FraudPreventionService
{
    /**
     * Check if a payment request is suspicious.
     */
    public function checkPayment(array $paymentData, User $user): array
    {
        $riskScore = 0;
        $flags = [];

        // Check amount-based risks
        $amountRisk = $this->checkAmountRisk($paymentData['amount'], $user);
        $riskScore += $amountRisk['score'];
        $flags = array_merge($flags, $amountRisk['flags']);

        // Check frequency-based risks
        $frequencyRisk = $this->checkFrequencyRisk($user);
        $riskScore += $frequencyRisk['score'];
        $flags = array_merge($flags, $frequencyRisk['flags']);

        // Check user behavior risks
        $behaviorRisk = $this->checkUserBehaviorRisk($user);
        $riskScore += $behaviorRisk['score'];
        $flags = array_merge($flags, $behaviorRisk['flags']);

        // Check IP-based risks
        $ipRisk = $this->checkIpRisk();
        $riskScore += $ipRisk['score'];
        $flags = array_merge($flags, $ipRisk['flags']);

        // Determine risk level
        $riskLevel = $this->calculateRiskLevel($riskScore);

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'flags' => array_filter($flags), // Remove null values
            'allow_payment' => $riskLevel !== 'high',
            'require_verification' => $riskLevel === 'medium',
        ];
    }

    /**
     * Check amount-based risks.
     */
    private function checkAmountRisk(float $amount, User $user): array
    {
        $score = 0;
        $flags = [];

        // Check if amount is unusually high for user
        $avgOrderAmount = $this->getUserAverageOrderAmount($user);
        
        if ($avgOrderAmount > 0 && $amount > ($avgOrderAmount * 5)) {
            $score += 30;
            $flags[] = 'unusually_high_amount';
        }

        // Check if amount exceeds daily limits
        $dailySpent = $this->getUserDailySpent($user);
        $dailyLimit = config('payments.security.daily_limit', 500000);
        
        if (($dailySpent + $amount) > $dailyLimit) {
            $score += 50;
            $flags[] = 'daily_limit_exceeded';
        }

        // Check for round number amounts (potential fraud indicator)
        if ($amount >= 10000 && $amount % 1000 == 0) {
            $score += 10;
            $flags[] = 'round_amount_suspicious';
        }

        return [
            'score' => $score,
            'flags' => $flags,
        ];
    }

    /**
     * Check frequency-based risks.
     */
    private function checkFrequencyRisk(User $user): array
    {
        $score = 0;
        $flags = [];

        // Check payment frequency in last hour
        $paymentsLastHour = Payment::where('order_id', function($query) use ($user) {
            $query->select('id')
                  ->from('orders')
                  ->where('user_id', $user->id);
        })
        ->where('created_at', '>=', now()->subHour())
        ->count();

        if ($paymentsLastHour >= 5) {
            $score += 40;
            $flags[] = 'high_frequency_payments';
        }

        // Check failed payment attempts
        $failedPaymentsToday = Payment::where('order_id', function($query) use ($user) {
            $query->select('id')
                  ->from('orders')
                  ->where('user_id', $user->id);
        })
        ->where('status', Payment::STATUS_FAILED)
        ->where('created_at', '>=', now()->startOfDay())
        ->count();

        if ($failedPaymentsToday >= 3) {
            $score += 25;
            $flags[] = 'multiple_failed_attempts';
        }

        return [
            'score' => $score,
            'flags' => $flags,
        ];
    }

    /**
     * Check user behavior risks.
     */
    private function checkUserBehaviorRisk(User $user): array
    {
        $score = 0;
        $flags = [];

        // Check account age
        $accountAge = $user->created_at->diffInDays(now());
        
        if ($accountAge < 1) {
            $score += 20;
            $flags[] = 'new_account';
        }

        // Check if user has completed orders
        $completedOrders = $user->orders()->where('status', 'delivered')->count();
        
        if ($completedOrders == 0) {
            $score += 15;
            $flags[] = 'no_order_history';
        }

        // Check email verification
        if (!$user->email_verified_at) {
            $score += 25;
            $flags[] = 'unverified_email';
        }

        return [
            'score' => $score,
            'flags' => $flags,
        ];
    }

    /**
     * Check IP-based risks.
     */
    private function checkIpRisk(): array
    {
        $score = 0;
        $flags = [];

        $clientIp = request()->ip();

        // Check if IP has made too many payment attempts
        $cacheKey = "payment_attempts_ip_{$clientIp}";
        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= 10) {
            $score += 35;
            $flags[] = 'high_ip_attempts';
        }

        // Increment attempt counter
        Cache::put($cacheKey, $attempts + 1, now()->addHour());

        // Check for known suspicious IP patterns (this would integrate with external services)
        if ($this->isKnownSuspiciousIp($clientIp)) {
            $score += 50;
            $flags[] = 'suspicious_ip';
        }

        return [
            'score' => $score,
            'flags' => $flags,
        ];
    }

    /**
     * Calculate risk level based on score.
     */
    private function calculateRiskLevel(int $score): string
    {
        if ($score >= 70) {
            return 'high';
        } elseif ($score >= 30) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get user's average order amount.
     */
    private function getUserAverageOrderAmount(User $user): float
    {
        return $user->orders()
            ->where('status', '!=', 'cancelled')
            ->avg('total_amount') ?? 0;
    }

    /**
     * Get user's daily spent amount.
     */
    private function getUserDailySpent(User $user): float
    {
        $orderPayments = Payment::where('order_id', function($query) use ($user) {
            $query->select('id')
                  ->from('orders')
                  ->where('user_id', $user->id);
        })
        ->where('status', Payment::STATUS_COMPLETED)
        ->where('created_at', '>=', now()->startOfDay())
        ->sum('amount') ?? 0;

        $preorderPayments = Payment::where('preorder_id', function($query) use ($user) {
            $query->select('id')
                  ->from('preorders')
                  ->where('user_id', $user->id);
        })
        ->where('status', Payment::STATUS_COMPLETED)
        ->where('created_at', '>=', now()->startOfDay())
        ->sum('amount') ?? 0;

        return $orderPayments + $preorderPayments;
    }

    /**
     * Check if IP is known to be suspicious.
     */
    private function isKnownSuspiciousIp(string $ip): bool
    {
        // This would integrate with external fraud detection services
        // For now, we'll implement basic checks
        
        // Check against local blacklist
        $blacklistedIps = Cache::get('blacklisted_ips', []);
        
        return in_array($ip, $blacklistedIps);
    }

    /**
     * Log fraud attempt.
     */
    public function logFraudAttempt(array $paymentData, User $user, array $fraudCheck): void
    {
        Log::warning('Potential fraud attempt detected', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'payment_amount' => $paymentData['amount'],
            'risk_score' => $fraudCheck['risk_score'],
            'risk_level' => $fraudCheck['risk_level'],
            'flags' => $fraudCheck['flags'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Block suspicious IP.
     */
    public function blockIp(string $ip, string $reason = 'Suspicious activity'): void
    {
        $blacklistedIps = Cache::get('blacklisted_ips', []);
        $blacklistedIps[] = $ip;
        
        Cache::put('blacklisted_ips', array_unique($blacklistedIps), now()->addDays(30));
        
        Log::info('IP address blocked', [
            'ip' => $ip,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Require additional verification for payment.
     */
    public function requireVerification(User $user, array $paymentData): array
    {
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        
        // Store verification requirement
        Cache::put("payment_verification_{$token}", [
            'user_id' => $user->id,
            'payment_data' => $paymentData,
            'expires_at' => now()->addMinutes(15),
        ], now()->addMinutes(15));

        return [
            'verification_required' => true,
            'verification_token' => $token,
            'verification_methods' => ['email', 'sms'],
            'expires_in' => 900, // 15 minutes
        ];
    }

    /**
     * Verify payment with token.
     */
    public function verifyPaymentToken(string $token, string $verificationCode): array
    {
        $verificationData = Cache::get("payment_verification_{$token}");
        
        if (!$verificationData) {
            return [
                'success' => false,
                'error' => 'Verification token expired or invalid',
            ];
        }

        // In a real implementation, you would verify the code sent via email/SMS
        // For now, we'll accept any 6-digit code
        if (strlen($verificationCode) !== 6 || !is_numeric($verificationCode)) {
            return [
                'success' => false,
                'error' => 'Invalid verification code',
            ];
        }

        // Remove verification requirement
        Cache::forget("payment_verification_{$token}");

        return [
            'success' => true,
            'payment_data' => $verificationData['payment_data'],
        ];
    }
}