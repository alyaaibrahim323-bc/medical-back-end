<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\Package;
use App\Models\UserPackage;
use App\Models\SingleSessionOffer;
use App\Services\KashierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
   public function createKashier(Request $r, KashierService $kashier)
{
    Log::info('PAYMENT_REQUEST_DEBUG', [
        'user' => $r->user()?->id,
        'input' => $r->all(),
    ]);

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
    $currency = strtoupper((string) $kashier->currency());
    // ✅ this will be returned to Flutter like the screenshot
    $summary = [
        'base_fee_cents' => 0,
        'service_fee_cents' => 0,
        'discount_cents' => 0,
        'total_cents' => 0,
        'discount_percent' => 0,
    ];

    if ($data['purpose'] === Payment::PURPOSE_SINGLE_SESSION) {

        $session = TherapySession::with('therapist')
        ->where('user_id', $user->id)
        ->findOrFail($data['id']);

    if (($session->status ?? null) !== 'pending_payment') {
        return response()->json(['message' => 'Session not payable'], 422);
    }

    // الربط
    $therapistId = $session->therapist_id;
    $therapySessionId = $session->id;

    // ✅ لازم offer يكون موجود
    $offer = SingleSessionOffer::query()
        ->where('therapist_id', $session->therapist_id)
        ->where('is_active', true)
        ->orderByDesc('id')
        ->first();

    if (!$offer) {
        return response()->json([
            'message' => 'No active offer for this therapist'
        ], 422);
    }

    $region  = strtoupper((string) ($user->pricing_region ?? ''));
    $isLocal = ($region === 'EG_LOCAL');

    $baseFee = $isLocal
        ? (int) ($offer->price_cents_egp ?? 0)
        : (int) ($offer->price_cents_usd ?? 0);

    if ($baseFee <= 0) {
        $baseFee = (int) ($offer->price_cents ?? 0);
    }

    $currency = $isLocal ? 'EGP' : 'USD';

    $discountPercent = (float) ($offer->discount_percent ?? 0);
    $discountPercent = max(0, min(100, $discountPercent));

    $discountCents = (int) round($baseFee * $discountPercent / 100);

    $netSessionFee = max(0, $baseFee - $discountCents);

    $serviceFee = (int) config('fees.single_session_service_cents', 0);

    $amountCents = $netSessionFee + $serviceFee;

    $summary = [
        'base_fee_cents' => $baseFee,
        'service_fee_cents' => $serviceFee,
        'discount_amount_cents' => $discountCents,
        'total_cents' => $amountCents,
    ];

    $payload = [
        'type' => 'single_session',
        'session_id' => $session->id,
        'therapist_id' => $session->therapist_id,

        'base_fee_cents' => $baseFee,
        'discount_percent' => $discountPercent,
        'discount_amount_cents' => $discountCents,
        'net_session_fee_cents' => $netSessionFee,

        'service_fee_cents' => $serviceFee,
        'offer_id' => $offer->id,

        'summary' => $summary,
    ];


    } else {

    $package = Package::where('is_active', true)->findOrFail($data['id']);
    $therapistId = $package->created_by_therapist_id;

    $hasActiveNotFinished = UserPackage::query()
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->whereColumn('sessions_used', '<', 'sessions_total')
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->exists();

    if ($hasActiveNotFinished) {
        return response()->json([
            'message' => 'You already have an active package. Please finish it before purchasing another one.'
        ], 422);
    }

    // ✅ زي single session: region based currency (لو باكدج عملتها ثابتة سيبيها بس)
    $region  = strtoupper((string) ($user->pricing_region ?? ''));
    $isLocal = ($region === 'EG_LOCAL');

    // ✅ baseFee (قبل الخصم)
    $baseFee = (int) $package->price_cents;

    // ✅ currency
    $currency = strtoupper((string) ($package->currency ?: $kashier->currency()));

    // ✅ discount percent
    $discountPercent = (float) ($package->discount_percent ?? 0);
    $discountPercent = max(0, min(100, $discountPercent));

    // ✅ discount amount
    $discountCents = (int) round($baseFee * $discountPercent / 100);

    // ✅ net price after discount
    $netFee = max(0, $baseFee - $discountCents);

    // ✅ service fee
    $serviceFee = (int) config('fees.package_service_cents', 0);

    // ✅ total payable
    $amountCents = $netFee + $serviceFee;

    // ✅ summary (نفس single session style)
    $summary = [
        'base_fee_cents'        => $baseFee,
        'service_fee_cents'     => $serviceFee,
        'discount_amount_cents' => $discountCents,
        'total_cents'           => $amountCents,
        'discount_percent'      => $discountPercent,
    ];

    $payload = [
        'type'                 => 'package',
        'package_id'           => $package->id,

        'base_fee_cents'       => $baseFee,
        'discount_percent'     => $discountPercent,
        'discount_amount_cents'=> $discountCents,
        'net_fee_cents'        => $netFee,

        'service_fee_cents'    => $serviceFee,
        'summary'              => $summary,
    ];
}


    // Kashier expects amount in smallest unit (piasters)
    $amount = (string) $amountCents;

    $reference = strtoupper(Str::random(10));

    $payment = DB::transaction(function () use (
        $user, $therapistId, $therapySessionId, $userPackageId, $data, $amountCents, $reference, $payload,$currency
    ) {
        return Payment::create([
            'user_id' => $user->id,
            'therapist_id' => $therapistId,
            'therapy_session_id' => $therapySessionId,
            'user_package_id' => $userPackageId,
            'purpose' => $data['purpose'],
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'provider' => 'kashier',
            'status' => Payment::STATUS_PENDING,
            'reference' => $reference,
            'payload' => $payload,
        ]);
    });

    $merchantId = $kashier->merchantId();
    $apiKey     = $kashier->apiKey();
    $currency   = $currency ?? $kashier->currency();
    $order      = $payment->reference;

    $hash = $kashier->makeHash($merchantId, $order, $amount, $currency);

    $params = [
        'merchantId' => $merchantId,
        'apiKey'     => $apiKey,
        'order'      => $order,
        'amount'     => $amount,
        'currency'   => $currency,
        'hash'       => $hash,
        'mode'       => $kashier->mode(),

        'merchantRedirect' => $kashier->redirectUrl(),
        'serverWebhook'    => $kashier->webhookUrl(),

        'display' => 'en',
        'allowedMethods' => 'card',
        'shopperReference' => (string) $user->id,
    ];

    $checkoutUrl = $kashier->buildPaymentUrl($params);

    Log::info('KASHIER_CREATE_PAYMENT', [
        'payment_id' => $payment->id,
        'reference' => $order,
        'amount' => $amount,
        'currency' => $currency,
        'checkout_url' => $checkoutUrl,
        'summary' => $summary,
    ]);

    return response()->json([
        'message' => 'Payment initiated',
        'data' => [
            'payment_id' => $payment->id,
            'reference' => $order,
            'purpose' => $data['purpose'],
            'currency' => $currency,
            'amount_cents' => $payment->amount_cents,
            'checkout_url' => $checkoutUrl,
            'summary' => $summary,
        ]
    ], 201);
}

    public function callback(Request $r)
    {
        $incoming = $r->all();
        Log::info('KASHIER_CALLBACK', $incoming);

        // ✅ وسّع القراءة
        $order = (string) (
            $incoming['order'] ??
            $incoming['merchantOrderId'] ??
            $incoming['orderId'] ??
            $incoming['order_id'] ??
            ''
        );

        if (!$order) {
            return response()->json(['message' => 'Missing order'], 422);
        }

        $payment = Payment::where('reference', $order)->first();
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

         $success = (bool)($incoming['success'] ?? $incoming['paid'] ?? false);
        $status = strtoupper((string)($incoming['paymentStatus'] ?? $incoming['status'] ?? ''));
        if ($status !== '') {
            $success = in_array($status, ['SUCCESS', 'PAID', 'APPROVED', 'COMPLETED'], true);
        }

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

    public function webhook(Request $r)
    {
        $incoming = $r->all();
        Log::info('KASHIER_WEBHOOK', $incoming);

        $order = (string) (
            $incoming['data']['order'] ??
            $incoming['data']['merchantOrderId'] ??
            $incoming['data']['orderId'] ??
            $incoming['data']['order_id'] ??
            ''
        );



       $payment = Payment::where('reference', $order)->first();
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $status = strtoupper((string)($incoming['data']['status']  ?? ''));
            $success = in_array($status, ['SUCCESS', 'PAID', 'APPROVED', 'COMPLETED'], true);

        DB::transaction(function () use ($incoming, $order, $success) {
            $payment = Payment::where('reference', $order)->lockForUpdate()->firstOrFail();

            if (in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_FAILED], true)) {
                return;
            }

            $payment->update([
                'status' => $success ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
                'paid_at' => $success ? now() : null,
                'failed_at' => $success ? null : now(),
                'provider_transaction_id' => (string)($incoming['data']['transactionId'] ?? $payment->provider_transaction_id),
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
    // ✅ Single session paid -> confirm session + notify
    if ($payment->purpose === Payment::PURPOSE_SINGLE_SESSION && $payment->therapy_session_id) {

        $session = TherapySession::with('therapist.user')->find($payment->therapy_session_id);

        if ($session && ($session->status ?? null) !== TherapySession::STATUS_CONFIRMED) {
            $session->update(['status' => TherapySession::STATUS_CONFIRMED]);
        }

        app(\App\Services\NotificationService::class)->sendToUser(
            $payment->user_id,
            'payment_success_session',
            [
                'title' => 'تم الدفع بنجاح',
                'message' => 'تم تأكيد حجز الجلسة بنجاح.',
                'session_id'   => $session?->id,
                'doctor'       => optional($session?->therapist?->user)->name,
                'scheduled_at' => optional($session?->scheduled_at)?->toISOString(),
                'amount_cents' => $payment->amount_cents,
                'currency'     => $payment->currency,
                'method'       => data_get($payment->payload, 'kashier_webhook.data.method'),
            ]
        );

        return;
    }

    // ✅ Package paid -> create user package + notify
    if ($payment->purpose === Payment::PURPOSE_PACKAGE) {

        // already processed
        if ($payment->user_package_id) return;

        $packageId = data_get($payment->payload, 'package_id');
        if (!$packageId) return;

        $package = Package::find($packageId);
        if (!$package) return;

        $userPackage = UserPackage::create([
            'user_id'        => $payment->user_id,
            'package_id'     => $package->id,
            'therapist_id'   => $payment->therapist_id,
            'sessions_total' => $package->sessions_count,
            'sessions_used'  => 0,
            'status'         => 'active',
            'purchased_at'   => now(),
            'expires_at'     => $package->validity_days ? now()->addDays($package->validity_days) : null,
        ]);

        $payment->update(['user_package_id' => $userPackage->id]);

        app(\App\Services\NotificationService::class)->sendToUser(
            $payment->user_id,
            'payment_success_package',
            [
                'title' => 'تم الدفع بنجاح',
                'message' => 'تم تفعيل الباكدج بنجاح.',
                'user_package_id' => $userPackage->id,
                'package_id'      => $package->id,
                'sessions_total'  => $userPackage->sessions_total,
                'expires_at'      => optional($userPackage->expires_at)?->toISOString(),
                'amount_cents'    => $payment->amount_cents,
                'currency'        => $payment->currency,
                'method'          => data_get($payment->payload, 'kashier_webhook.data.method'),
            ]
        );

        return;
    }
}

}
