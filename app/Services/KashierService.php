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

    // ✅ دي الـ API Key بتاعة Kashier (مش webhook secret)
    public function apiKey(): string
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

    // ✅ payment string لازم يكون MID.orderId.amount.currency
    // ✅ amount هنا لازم يكون integer string (cents)
    public function buildPaymentString(string $merchantId, string $orderId, string $amountCents, string $currency): string
    {
        return "{$merchantId}.{$orderId}.{$amountCents}.{$currency}";
    }

    // ✅ hash = HMAC-SHA256(paymentString, apiKey)
    public function makeHashFromPaymentString(string $paymentString): string
    {
        Log::info('KASHIER_HASH_DEBUG', [
            'payment' => $paymentString,
            'api_key_length' => strlen($this->apiKey()),
        ]);

        return hash_hmac('sha256', $paymentString, $this->apiKey());
    }

    public function checkoutUrl(array $params): string
    {
        return $this->baseUrl() . '/?' . http_build_query($params);
    }

    /**
     * Webhook verification:
     * Kashier بتبعت signature/hash في الويبهوك. لو انت مش متأكد من الفورمات حالياً:
     * خليها false لحد ما تاخد payload فعلي من Kashier وتثبت صيغة التوقيع.
     */
    public function shouldVerifyWebhook(): bool
    {
        return (bool) config('services.kashier.verify_webhook', false);
    }

    public function verifyWebhook(array $data): bool
    {
        $receivedHash = (string)($data['hash'] ?? '');
        if ($receivedHash === '') return false;

        // نحاول نعيد بناء paymentString:
        // الأفضل لو Kashier بترسل `payment` جاهز
        $paymentString = (string)($data['payment'] ?? '');

        // أو نبنيه من merchantOrderId + amount + currency (لو موجودين)
        if ($paymentString === '') {
            $orderId  = (string)($data['merchantOrderId'] ?? $data['orderId'] ?? $data['order'] ?? '');
            $amount   = (string)($data['amount'] ?? '');
            $currency = (string)($data['currency'] ?? 'EGP');

            if ($orderId !== '' && $amount !== '') {
                // ⚠️ هنا لازم تتأكد: هل amount في الويبهوك cents ولا decimal؟
                // لو جالك decimal "1971.54" حوّله لـ "197154" قبل البناء.
                $amountNormalized = str_contains($amount, '.') ? str_replace('.', '', $amount) : $amount;

                $paymentString = $this->buildPaymentString($this->merchantId(), $orderId, $amountNormalized, $currency);
            }
        }

        if ($paymentString === '') return false;

        $calculated = $this->makeHashFromPaymentString($paymentString);
        return hash_equals($calculated, $receivedHash);
    }
}
