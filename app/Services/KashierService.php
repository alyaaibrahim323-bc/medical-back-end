<?php

namespace App\Services;

class KashierService
{
    public function baseUrl(): string
    {
        return 'https://payments.kashier.io';
    }

    public function merchantId(): string
    {
        return config('services.kashier.merchant_id');
    }

    // ✅ Payment API Key (مش Secret Key)
    public function apiKey(): string
    {
        return trim(config('services.kashier.api_key'));
    }

    public function mode(): string
    {
        return config('services.kashier.mode', 'test');
    }

    public function redirectUrl(): string
    {
        return config('services.kashier.redirect_url');
    }

    public function webhookUrl(): string
    {
        return config('services.kashier.webhook_url');
    }

    /**
     * ✅ HASH = HMAC_SHA256("merchantId.order.amount.currency", API_KEY)
     */
    public function makeHash(
        string $merchantId,
        string $order,
        int $amount,
        string $currency
    ): string {
        $message = "{$merchantId}.{$order}.{$amount}.{$currency}";
        return hash_hmac('sha256', $message, $this->apiKey());
    }

    public function checkoutUrl(array $params): string
    {
        return $this->baseUrl() . '/?' . http_build_query($params);
    }
}
