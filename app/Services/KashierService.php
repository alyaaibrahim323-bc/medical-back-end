<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class KashierService
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.kashier.base_url'), '/');
    }

    public function merchantId(): string
    {
        return (string) config('services.kashier.merchant_id');
    }

    public function secretKey(): string
    {
        // مهم: trim عشان أي newline في env يكسر الهاش
        return trim((string) config('services.kashier.secret_key'));
    }

    public function mode(): string
    {
        return (string) config('services.kashier.mode', 'test');
    }

    public function redirectUrl(): string
    {
        return (string) config('services.kashier.redirect_url');
    }

    public function webhookUrl(): string
    {
        return (string) config('services.kashier.webhook_url');
    }

    /**
     * ✅ HPP Hash = HMAC-SHA256("merchantId.order.amount.currency", secretKey)
     */
    public function makeHash(string $merchantId, string $order, int $amount, string $currency): string
    {
        $message = "{$merchantId}.{$order}.{$amount}.{$currency}";

        Log::info('KASHIER_HASH_DEBUG', [
            'message' => $message,
            'secret_length' => strlen($this->secretKey()),
        ]);

        return hash_hmac('sha256', $message, $this->secretKey());
    }

    public function checkoutUrl(array $params): string
    {
        return $this->baseUrl() . '/?' . http_build_query($params);
    }
}
