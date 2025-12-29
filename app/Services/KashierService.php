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

    public function apiKey(): string
    {
        return (string) config('services.kashier.api_key');
    }

    public function secretKey(): string
    {
        return (string) config('services.kashier.secret_key');
    }

    public function mode(): string
    {
        return (string) config('services.kashier.mode', 'test');
    }

    public function currency(): string
    {
        return (string) config('services.kashier.currency', 'EGP');
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
     * Kashier hash غالبًا بيكون HMAC-SHA256 على:
     * merchantId + order + amount + currency (بنفس الترتيب)
     * (وفي integrations كتير بيستخدموا نقطة "." للفصل)
     */
    public function makeHash(string $merchantId, string $order, string $amount, string $currency): string
    {
        $message = "{$merchantId}.{$order}.{$amount}.{$currency}";

        Log::info('KASHIER_HASH_DEBUG', [
            'message' => $message,
            'secret_len' => strlen($this->apiKey()),
        ]);
            $path = "/?payment=".$message;


        return hash_hmac('sha256', $path, $this->apiKey(),false);
    }

    public function buildPaymentUrl(array $params): string
    {
        return $this->baseUrl() . '/?' . http_build_query($params);
    }
}
