<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Package;
use App\Models\TherapySession;
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

    // ===== احسب المبلغ (قروش) =====
    $amountCents = 2465; // مثال – انتِ أصلاً عندك الحساب ده صح

    // order / reference
    $order = strtoupper(Str::random(10));

    // خزّني Payment في DB (زي ما عندك)
    $payment = Payment::create([
        'user_id' => $user->id,
        'purpose' => $data['purpose'],
        'amount_cents' => $amountCents,
        'currency' => 'EGP',
        'provider' => 'kashier',
        'status' => Payment::STATUS_PENDING,
        'reference' => $order,
    ]);

    // ===== Kashier Params =====
    $merchantId = $kashier->merchantId();
    $currency   = 'EGP';

    // ✅ HASH الصح
    $hash = $kashier->makeHash(
        $merchantId,
        $order,
        $amountCents,
        $currency
    );

    // ✅ اللينك الصح (من غير payment=)
    $params = [
        'merchantId' => $merchantId,
        'order'      => $order,
        'amount'     => $amountCents, // cents
        'currency'   => $currency,
        'hash'       => $hash,
        'mode'       => $kashier->mode(),
        'merchantRedirect' => $kashier->redirectUrl(),
        'serverWebhook'    => $kashier->webhookUrl(),
        'display'          => 'en',
        'allowedMethods'   => 'card,wallet,bank_installments',
        'shopperReference' => (string) $user->id,
    ];

    $checkoutUrl = $kashier->checkoutUrl($params);

    return response()->json([
        'checkout_url' => $checkoutUrl,
        'order' => $order,
        'amount' => $amountCents,
    ]);
}


    public function callback(Request $r)
    {
        $incoming = $r->all();
        Log::info('KASHIER_CALLBACK', $incoming);

        // ✅ التوسيع عشان "Missing order"
        $order = (string) (
            $incoming['merchantOrderId']
            ?? $incoming['orderId']
            ?? $incoming['order']
            ?? $incoming['order_id']
            ?? $incoming['merchant_order_id']
            ?? ''
        );

        if (!$order) {
            return response()->json(['message' => 'Missing order', 'raw' => $incoming], 422);
        }

        $payment = Payment::where('reference', $order)->first();
        if (!$payment) return response()->json(['message' => 'Payment not found'], 404);

        // Kashier عادة بيرجع paymentStatus
        $statusRaw = strtoupper((string)($incoming['paymentStatus'] ?? $incoming['status'] ?? ''));
        $success = in_array($statusRaw, ['SUCCESS','PAID','APPROVED','COMPLETED'], true);

        if ($success && $payment->status !== Payment::STATUS_PAID) {
            DB::transaction(function () use ($payment, $incoming) {
                $payment->update([
                    'status' => Payment::STATUS_PAID,
                    'paid_at' => now(),
                    'provider_transaction_id' => (string)($incoming['transactionId'] ?? $incoming['transaction_id'] ?? $payment->provider_transaction_id),
                    'payload' => array_merge($payment->payload ?? [], ['kashier_callback' => $incoming]),
                ]);

                $this->applyBusinessLogicAfterPaid($payment->fresh());
            });
        }

        return response()->json([
            'message' => 'Callback received',
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

        $order = (string) (
            $incoming['merchantOrderId']
            ?? $incoming['orderId']
            ?? $incoming['order']
            ?? $incoming['order_id']
            ?? ''
        );

        if (!$order) {
            return response()->json(['message' => 'Missing order', 'raw' => $incoming], 422);
        }

        $statusRaw = strtoupper((string)($incoming['paymentStatus'] ?? $incoming['status'] ?? ''));
        $success = in_array($statusRaw, ['SUCCESS','PAID','APPROVED','COMPLETED'], true);

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
