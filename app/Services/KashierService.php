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

    public function merchantRedirect(): string
    {
        return (string) config('services.kashier.redirect_url');
    }

    public function formatAmountFromCents(int $amountCents): string
    {
        // جرّبي ده أولاً: "150.00"
        return number_format($amountCents / 100, 2, '.', '');
    }

    public function checkoutUrl(array $params): string
    {
        return $this->baseUrl() . '/?' . http_build_query($params);
    }

    /**
     * Kashier expects params: merchantId, order, amount, currency, mode, merchantRedirect, hash
     * We'll hash same sequence including merchantRedirect (to match your old logic but correct naming)
     */
    public function makeHash(array $params): string
    {
        $merchantId       = (string)($params['merchantId'] ?? '');
        $order            = (string)($params['order'] ?? '');
        $amount           = (string)($params['amount'] ?? '');
        $currency         = (string)($params['currency'] ?? '');
        $merchantRedirect = (string)($params['merchantRedirect'] ?? '');

        $raw = $merchantId . $order . $amount . $currency . $merchantRedirect . $this->secret();
        return hash('sha256', $raw);
    }
    public function redirectUrl(): string
{
    return (string) config('services.kashier.redirect_url');
}

}
