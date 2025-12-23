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
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
    /**
     * POST /api/payments/kashier
     * body:
     * { "purpose":"single_session","id":12 }
     * { "purpose":"package","id":3 }
     */
    public function createKashier(Request $r, KashierService $kashier)
    {
        $data = $r->validate([
            'purpose' => ['required','in:single_session,package'],
            'id'      => ['required','integer'],
        ]);

        $user = $r->user();

        // 1) compute amount + payload (بدون تفاصيل UI)
        $therapistId = null;
        $therapySessionId = null;
        $userPackageId = null;
        $amount = 0;
        $payload = [];

        if ($data['purpose'] === Payment::PURPOSE_SINGLE_SESSION) {
            $session = TherapySession::with('therapist')
                ->where('user_id', $user->id)
                ->findOrFail($data['id']);

            if (($session->status ?? null) !== 'pending') {
                return response()->json(['message' => 'Session not payable'], 422);
            }

            $sessionFee = (int) ($session->therapist->price_cents ?? 0);
            $serviceFee = (int) config('fees.single_session_service_cents', 0);
            $amount = $sessionFee + $serviceFee;

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

            $serviceFee = (int) config('fees.package_service_cents', 0);
            $amount = $payable + $serviceFee;

            $payload = [
                'type' => 'package',
                'package_id' => $package->id,
                'base_price_cents' => $basePrice,
                'discount_percent' => $discount,
                'payable_cents' => $payable,
                'service_fee_cents' => $serviceFee,
            ];
        }

        // 2) create pending payment
        $reference = strtoupper(Str::random(10));

        $payment = DB::transaction(function () use (
            $user, $therapistId, $therapySessionId, $userPackageId, $data, $amount, $reference, $payload
        ) {
            return Payment::create([
                'user_id' => $user->id,
                'therapist_id' => $therapistId,
                'therapy_session_id' => $therapySessionId,
                'user_package_id' => $userPackageId,
                'purpose' => $data['purpose'],
                'amount_cents' => $amount,
                'currency' => 'EGP',
                'provider' => 'kashier',
                'status' => Payment::STATUS_PENDING,
                'reference' => $reference,
                'payload' => $payload,
            ]);
        });

        // 3) build checkout URL params
        $params = [
            'merchantId'  => $kashier->merchantId(),
            'orderId'     => $payment->reference,       // ✅ merchant order reference
            'amount'      => $payment->amount_cents,    // ✅ cents
            'currency'    => $payment->currency,
            'mode'        => $kashier->mode(),
            'redirectUrl' => $kashier->redirectUrl(),
            // 'webhookUrl'  => $kashier->webhookUrl(),  // لو Kashier بيسمح
        ];

        // 4) sign
        $params['signature'] = $kashier->makeSignature($params);

        // 5) return checkout url
        $checkoutUrl = $kashier->checkoutUrl($params);

        return response()->json([
            'message' => 'Payment initiated',
            'data' => [
                'payment_id'  => $payment->id,
                'reference'   => $payment->reference,
                'amount_cents'=> $payment->amount_cents,
                'currency'    => $payment->currency,
                'checkout_url'=> $checkoutUrl,
            ]
        ], 201);
    }

    /**
     * GET /api/payments/kashier/callback
     * ده redirect للـ user بعد الدفع (UI). Source of truth: webhook.
     */
    public function callback(Request $r, KashierService $kashier)
    {
        $incoming = $r->all();

        // signature verify if provided
        $sig = (string)($incoming['signature'] ?? $incoming['hash'] ?? '');
        if ($sig && !$kashier->verifySignature($incoming, $sig)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $orderId = (string)($incoming['orderId'] ?? '');
        if (!$orderId) return response()->json(['message' => 'Missing orderId'], 422);

        $payment = Payment::where('reference', $orderId)->first();
        if (!$payment) return response()->json(['message' => 'Payment not found'], 404);

        return response()->json([
            'message' => 'Callback received',
            'data' => [
                'reference' => $payment->reference,
                'status'    => $payment->status,
                'raw'       => $incoming,
            ],
        ]);
    }

    /**
     * POST /api/payments/kashier/webhook
     * ده اللي بيحدد paid/failed بشكل نهائي.
     */
    public function webhook(Request $r, KashierService $kashier)
    {
        $incoming = $r->all();

        $sig = (string)($incoming['signature'] ?? $incoming['hash'] ?? '');
        if ($sig && !$kashier->verifySignature($incoming, $sig)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $orderId = (string)($incoming['orderId'] ?? '');
        if (!$orderId) return response()->json(['message' => 'Missing orderId'], 422);

        DB::transaction(function () use ($incoming, $orderId) {
            /** @var Payment $payment */
            $payment = Payment::where('reference', $orderId)->lockForUpdate()->firstOrFail();

            // ✅ idempotent
            if ($payment->status === Payment::STATUS_PAID) {
                return;
            }

            // TODO: map Kashier success flag
            $success = (bool)($incoming['success'] ?? $incoming['paid'] ?? false);

            $payment->update([
                'status' => $success ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
                'paid_at' => $success ? now() : null,
                'failed_at' => $success ? null : now(),
                'provider_order_id' => (string)($incoming['kashierOrderId'] ?? $payment->provider_order_id),
                'provider_transaction_id' => (string)($incoming['transactionId'] ?? $payment->provider_transaction_id),
                'provider_payment_id' => (string)($incoming['paymentId'] ?? $payment->provider_payment_id),
                'payload' => array_merge($payment->payload ?? [], ['kashier_webhook' => $incoming]),
            ]);

            if ($success) {
                $this->applyBusinessLogicAfterPaid($payment->fresh());
            }
        });

        return response()->json(['message' => 'ok']);
    }

    /**
     * ✅ Confirm session OR create user_package
     */
    protected function applyBusinessLogicAfterPaid(Payment $payment): void
    {
        // خليها idempotent (متكررش)
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
