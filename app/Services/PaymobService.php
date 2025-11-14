<?php

// app/Services/PaymobService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaymobService
{
    protected string $base;
    public function __construct()
    {
        $this->base = rtrim(config('services.paymob.base_url','https://accept.paymob.com/api'), '/');
    }

    public function auth(): string
    {
        $apiKey = config('services.paymob.api_key');
        $res = Http::post("{$this->base}/auth/tokens", ['api_key'=>$apiKey])->json();
        if (empty($res['token'])) throw new \RuntimeException('Paymob auth failed');
        return $res['token'];
    }

    public function createOrder(string $token, int $amountCents, string $merchantOrderId, array $items=[]): array
    {
        return Http::withToken($token)->post("{$this->base}/ecommerce/orders", [
            'amount_cents' => $amountCents,
            'currency'     => 'EGP',
            'merchant_order_id' => $merchantOrderId,
            'items'        => $items,
        ])->json();
    }

    public function paymentKey(string $token, int $amountCents, int $orderId, array $billingData, int $integrationId): array
    {
        return Http::withToken($token)->post("{$this->base}/acceptance/payment_keys", [
            'amount_cents'  => $amountCents,
            'currency'      => 'EGP',
            'order_id'      => $orderId,
            'billing_data'  => $billingData,
            'integration_id'=> $integrationId,
            'expiration'    => 3600,
        ])->json();
    }

    // تحقق HMAC (Webhook)
    public function verifyHmac(array $data, string $hmac): bool
    {
        $hmacKey = config('services.paymob.hmac');
        // ترتيب الحقول طبقًا لتوثيق Paymob
        $ordered = [
            'amount_cents','created_at','currency','error_occured','has_parent_transaction',
            'id','integration_id','is_3d_secure','is_auth','is_capture','is_refunded','is_standalone_payment',
            'is_voided','order.id','owner','pending','source_data.pan','source_data.sub_type','source_data.type',
            'success'
        ];
        $concat = '';
        foreach ($ordered as $k) {
            $value = data_get($data, $k);
            $concat .= is_null($value) ? '' : (string)$value;
        }
        $calc = hash_hmac('sha512', $concat, $hmacKey);
        return hash_equals($calc, $hmac);
    }
}
