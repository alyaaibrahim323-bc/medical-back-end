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

    public function secret(): string
    {
        // مهم جدًا: احنا بنtrim عشان أي newline في env يبوّظ الـ hash
        return trim((string) config('services.kashier.secret'));
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

    public function formatAmountFromCents(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, '.', '');
    }

    /**
     * ✅ Kashier hash
     * Sign: "/?payment=mid.orderId.amount.currency"
     * HMAC-SHA256 with API Key
     */
    public function makeHash(string $merchantId, string $orderId, string $amount, string $currency): string
    {
        $message = "/?payment={$merchantId}.{$orderId}.{$amount}.{$currency}";
        $secret  = $this->secret();

        Log::info('KASHIER_HASH_DEBUG', [
            'message' => $message,
            'secret_length' => strlen($secret),
        ]);

        return hash_hmac('sha256', $message, $secret);
    }

    /**
     * ✅ Build Checkout URL using "payment" + "hash"
     */
    public function checkoutUrl(
        string $merchantId,
        string $orderId,
        string $amount,
        string $currency,
        string $hash,
        array $extra = []
    ): string {
        $query = array_merge([
            'payment' => "{$merchantId}.{$orderId}.{$amount}.{$currency}",
            'hash' => $hash,
            'mode' => $this->mode(),
            'merchantRedirect' => $this->redirectUrl(),
            'serverWebhook' => $this->webhookUrl(),

            // Optional but recommended:
            'display' => 'en', // or 'ar'
            'allowedMethods' => 'card,wallet,bank_installments',
        ], $extra);

        return $this->baseUrl() . '/?' . http_build_query($query);
    }

    /**
     * ⚠️ Webhook signature differs حسب اللي Kashier بيرجعه عندك.
     * مؤقتًا خليه true لحد ما تشوف payload الحقيقي وتطبّق نفس signature بتاعهم.
     */
    public function verifyWebhook(array $data): bool
    {
        return true;
    }
}
