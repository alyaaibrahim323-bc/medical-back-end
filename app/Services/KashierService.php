<?php

namespace App\Services;

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

    public function secret(): string
    {
        return (string) config('services.kashier.secret');
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
     * ✅ build hosted checkout url
     * NOTE: Kashier params may differ slightly (حسب إعدادات حسابك).
     */
    public function checkoutUrl(array $params): string
    {
        return $this->baseUrl() . '/?' . http_build_query($params);
    }

    /**
     * ❗ TODO: Replace with Kashier official signature formula.
     * هتجيبيها من داشبورد/Docs Kashier.
     */
    public function makeSignature(array $params): string
    {
        // مثال placeholder (غير مضمون):
        // sha256(merchantId + orderId + amount + currency + redirectUrl + secret)

        $merchantId  = $params['merchantId'] ?? '';
        $orderId     = $params['orderId'] ?? '';
        $amount      = $params['amount'] ?? '';
        $currency    = $params['currency'] ?? '';
        $redirectUrl = $params['redirectUrl'] ?? '';

        $raw = $merchantId.$orderId.$amount.$currency.$redirectUrl.$this->secret();
        return hash('sha256', $raw);
    }

    public function verifySignature(array $incoming, string $incomingSig): bool
    {
        // ❗ هنا كمان لازم تبقى حسب Kashier: إيه الحقول اللي بتتSigned في callback/webhook
        $expected = $this->makeSignature($incoming);
        return hash_equals($expected, $incomingSig);
    }
}
