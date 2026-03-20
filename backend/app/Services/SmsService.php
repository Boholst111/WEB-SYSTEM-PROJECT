<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SmsService
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        $this->provider = config('notifications.sms.provider', 'semaphore');
        $this->config = config("notifications.sms.{$this->provider}", []);
    }

    /**
     * Send SMS message.
     */
    public function send(string $phone, string $message): bool
    {
        if (!config('notifications.sms.enabled', true)) {
            Log::info('SMS notifications disabled', ['phone' => $phone]);
            return false;
        }

        // Check rate limiting
        if (!$this->checkRateLimit($phone)) {
            Log::warning('SMS rate limit exceeded', ['phone' => $phone]);
            return false;
        }

        try {
            $result = match($this->provider) {
                'semaphore' => $this->sendViaSemaphore($phone, $message),
                'itexmo' => $this->sendViaItexmo($phone, $message),
                default => throw new \Exception("Unsupported SMS provider: {$this->provider}")
            };

            if ($result) {
                $this->incrementRateLimit($phone);
                Log::info('SMS sent successfully', [
                    'phone' => $phone,
                    'provider' => $this->provider,
                    'message_length' => strlen($message)
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to send SMS', [
                'phone' => $phone,
                'provider' => $this->provider,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send SMS via Semaphore API.
     */
    private function sendViaSemaphore(string $phone, string $message): bool
    {
        $apiKey = $this->config['api_key'] ?? null;
        $senderName = $this->config['sender_name'] ?? 'DiecastEmp';
        $apiUrl = $this->config['api_url'] ?? 'https://api.semaphore.co/api/v4/messages';

        if (!$apiKey) {
            Log::error('Semaphore API key not configured');
            return false;
        }

        $response = Http::post($apiUrl, [
            'apikey' => $apiKey,
            'number' => $this->formatPhoneNumber($phone),
            'message' => $message,
            'sendername' => $senderName,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return isset($data['message_id']) || ($data['status'] ?? '') === 'success';
        }

        Log::error('Semaphore API error', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return false;
    }

    /**
     * Send SMS via Itexmo API.
     */
    private function sendViaItexmo(string $phone, string $message): bool
    {
        $apiCode = $this->config['api_code'] ?? null;
        $password = $this->config['password'] ?? null;
        $apiUrl = $this->config['api_url'] ?? 'https://www.itexmo.com/php_api/api.php';

        if (!$apiCode || !$password) {
            Log::error('Itexmo credentials not configured');
            return false;
        }

        $response = Http::asForm()->post($apiUrl, [
            '1' => $this->formatPhoneNumber($phone),
            '2' => $message,
            '3' => $apiCode,
            'passwd' => $password,
        ]);

        if ($response->successful()) {
            $result = $response->body();
            // Itexmo returns "0" for success
            return trim($result) === '0';
        }

        Log::error('Itexmo API error', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return false;
    }

    /**
     * Format phone number for Philippine SMS gateways.
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert to international format (63XXXXXXXXXX)
        if (strlen($phone) === 10) {
            // 9XXXXXXXXX -> 639XXXXXXXXX
            $phone = '63' . $phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            // 09XXXXXXXXX -> 639XXXXXXXXX
            $phone = '63' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Check if phone number has exceeded rate limit.
     */
    private function checkRateLimit(string $phone): bool
    {
        $key = "sms_rate_limit:{$phone}";
        $limit = config('notifications.rate_limits.sms_per_user_per_hour', 5);
        $count = Cache::get($key, 0);

        return $count < $limit;
    }

    /**
     * Increment rate limit counter for phone number.
     */
    private function incrementRateLimit(string $phone): void
    {
        $key = "sms_rate_limit:{$phone}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }

    /**
     * Send bulk SMS messages.
     */
    public function sendBulk(array $recipients): array
    {
        $results = [];
        $batchSize = config('notifications.rate_limits.bulk_sms_batch_size', 50);
        $batches = array_chunk($recipients, $batchSize);

        foreach ($batches as $batch) {
            foreach ($batch as $recipient) {
                $phone = $recipient['phone'];
                $message = $recipient['message'];
                
                $success = $this->send($phone, $message);
                
                $results[] = [
                    'phone' => $phone,
                    'success' => $success,
                ];

                // Small delay to avoid overwhelming the API
                usleep(100000); // 100ms
            }
        }

        return $results;
    }

    /**
     * Validate Philippine phone number format.
     */
    public function validatePhoneNumber(string $phone): bool
    {
        $formatted = $this->formatPhoneNumber($phone);
        
        // Philippine mobile numbers: 639XXXXXXXXX (12 digits starting with 639)
        return preg_match('/^639\d{9}$/', $formatted) === 1;
    }
}
