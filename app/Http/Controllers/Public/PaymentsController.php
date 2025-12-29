<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\Package;
use App\Models\UserPackage;
use App\Services\KashierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
    public function createKashier(Request $r, KashierService $kashier)
    {
        $data = $r->validate([
            'purpose' => ['required', 'in:single_session,package'],
            'id'      => ['required', 'integer'],
        ]);

        $user = $r->user();

        $therapistId = null;
        $therapySessionId = null;
        $userPackageId = null;
        $amountCents = 0;
        $payload = [];

        if ($data['purpose'] === Payment::PURPOSE_SINGLE_SESSION) {
            $session = TherapySession::with('therapist')
                ->where('user_id', $user->id)
                ->findOrFail($data['id']);

            if (($session->status ?? null) !== 'pending') {
                return response()->json(['message' => 'Session not payable'], 422);
            }

            $sessionFee = (int)($session->therapist->price_cents ?? 0);
            $serviceFee = (int) config('fees.single_session_service_cents', 0);

            $amountCents = $sessionFee + $serviceFee;

            $therapistId = $session->therapist_id;
            $therapySessionId = $session->id;

            $payload = [
                'type' => 'single_session',
                'session_fee_cents' => $sessionFee,
                'service_fee_cents' => $serviceFee,
                'session_id' => $session->id,
            ];
        } else {
            $package = Package::where('is_active', true)->findOrFail($data['id']);

            $therapistId = $package->created_by_therapist_id;

            $basePrice = (int) $package->price_cents;
            $discount  = (float) ($package->discount_percent ?? 0);
            $payable   = (int) round($basePrice * (100 - $discount) / 100);

            $serviceFee  = (int) config('fees.package_service_cents', 0);
            $amountCents = $payable + $serviceFee;

            $payload = [
                'type' => 'package',
                'package_id' => $package->id,
                'base_price_cents' => $basePrice,
                'discount_percent' => $discount,
                'payable_cents' => $payable,
                'service_fee_cents' => $serviceFee,
            ];
        }

        $reference = strtoupper(Str::random(10)); // order

        $payment = DB::transaction(function () use (
            $user, $therapistId, $therapySessionId, $userPackageId, $data, $amountCents, $reference, $payload
        ) {
            return Payment::create([
                'user_id' => $user->id,
                'therapist_id' => $therapistId,
                'therapy_session_id' => $therapySessionId,
                'user_package_id' => $userPackageId,
                'purpose' => $data['purpose'],
                'amount_cents' => $amountCents,
                'currency' => 'EGP',
                'provider' => 'kashier',
                'status' => Payment::STATUS_PENDING,
                'reference' => $reference,
                'payload' => $payload,
            ]);
        });

        // ✅ Kashier HPP params
        $merchantId = $kashier->merchantId();
        $order      = $payment->reference;

        // ✅ IMPORTANT: amount = cents integer
        $amount     = (int) $payment->amount_cents;

        $currency   = $payment->currency;

        $hash = $kashier->makeHash($merchantId, $order, $amount, $currency);

        $params = [
            'merchantId'       => $merchantId,
            'order'            => $order,          // ✅ order (not orderId)
            'amount'           => $amount,         // ✅ cents
            'currency'         => $currency,
            'hash'             => $hash,
            'mode'             => $kashier->mode(),
            'merchantRedirect' => $kashier->redirectUrl(),
            'serverWebhook'    => $kashier->webhookUrl(),

            // optional UI
            'display'          => 'en',
            'allowedMethods'   => 'card,wallet,bank_installments',
            'shopperReference' => (string) $user->id,
        ];

        $checkoutUrl = $kashier->checkoutUrl($params);

        Log::info('KASHIER_CREATE_PAYMENT', [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'amount_cents' => $payment->amount_cents,
            'hash_length' => strlen($hash),
            'checkout_url' => $checkoutUrl,
        ]);

        return response()->json([
            'message' => 'Payment initiated',
            'data' => [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'checkout_url' => $checkoutUrl,
            ]
        ], 201);
    }

    public function callback(Request $r)
    {
        $incoming = $r->all();
        Log::info('KASHIER_CALLBACK', $incoming);

        $order = $this->extractOrder($incoming);
        if (!$order) return response()->json(['message' => 'Missing order'], 422);

        $payment = Payment::where('reference', $order)->first();
        if (!$payment) return response()->json(['message' => 'Payment not found'], 404);

        return response()->json([
            'message' => 'Callback received (UI only)',
            'data' => [
                'reference' => $payment->reference,
                'status' => $payment->status,
                'raw' => $incoming,
            ],
        ]);
    }

    public function webhook(Request $r)
    {
        $incoming = $r->all();
        Log::info('KASHIER_WEBHOOK', $incoming);

        $order = $this->extractOrder($incoming);
        if (!$order) return response()->json(['message' => 'Missing order'], 422);

        $status = strtoupper((string)($incoming['paymentStatus'] ?? $incoming['status'] ?? ''));
        $success = in_array($status, ['SUCCESS', 'PAID', 'APPROVED', 'COMPLETED'], true)
            || (bool)($incoming['success'] ?? $incoming['paid'] ?? false);

        DB::transaction(function () use ($incoming, $order, $success) {
            $payment = Payment::where('reference', $order)->lockForUpdate()->firstOrFail();

            if (in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_FAILED], true)) {
                return;
            }

            $payment->update([
                'status' => $success ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
                'paid_at' => $success ? now() : null,
                'failed_at' => $success ? null : now(),
                'provider_transaction_id' => (string)($incoming['transactionId'] ?? $incoming['transaction_id'] ?? $payment->provider_transaction_id),
                'payload' => array_merge($payment->payload ?? [], ['kashier_webhook' => $incoming]),
            ]);

            if ($success) {
                $this->applyBusinessLogicAfterPaid($payment->fresh());
            }
        });

        return response()->json(['message' => 'ok']);
    }

    protected function extractOrder(array $incoming): ?string
    {
        $order = (string)(
            $incoming['merchantOrderId']
            ?? $incoming['order']
            ?? $incoming['orderId']
            ?? $incoming['order_id']
            ?? ''
        );

        return $order !== '' ? $order : null;
    }

    protected function applyBusinessLogicAfterPaid(Payment $payment): void
    {
        if ($payment->purpose === Payment::PURPOSE_SINGLE_SESSION && $payment->therapy_session_id) {
            $session = TherapySession::find($payment->therapy_session_id);
            if ($session && ($session->status ?? null) !== 'confirmed') {
                $session->update(['status' => 'confirmed']);
            }
            return;
        }

        if ($payment->purpose === Payment::PURPOSE_PACKAGE) {
            if ($payment->user_package_id) return;

            $packageId = $payment->payload['package_id'] ?? null;
            if (!$packageId) return;

            $package = Package::find($packageId);
            if (!$package) return;

            $userPackage = UserPackage::create([
                'user_id' => $payment->user_id,
                'package_id' => $package->id,
                'therapist_id' => $payment->therapist_id,
                'sessions_total' => $package->sessions_count,
                'sessions_used' => 0,
                'status' => 'active',
                'purchased_at' => now(),
                'expires_at' => $package->validity_days ? now()->addDays($package->validity_days) : null,
            ]);

            $payment->update(['user_package_id' => $userPackage->id]);
        }
    }
}
