<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\UserPackage;
use App\Models\Package;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentsController extends Controller
{
    /**
     * POST /payments
     * body:
     *   purpose: single_session | package
     *   id:      therapy_session_id OR package_id
     *   billing: {...}
     */
    public function create(Request $r, PaymobService $paymob)
    {
        $data = $r->validate([
            'purpose' => ['required','in:single_session,package'],
            'id'      => ['required','integer'],
            'billing' => ['required','array'],
        ]);

        $user = $r->user();

        // ========== 1) حدد الهدف والمبلغ ==========
        if ($data['purpose'] === Payment::PURPOSE_SINGLE_SESSION) {
            // ندور على الجلسة اللى اتعملت قبل كده فى TherapySessionController
            $session = TherapySession::with('therapist')
                ->where('user_id', $user->id)
                ->findOrFail($data['id']);

            if ($session->status !== TherapySession::STATUS_PENDING) {
                return response()->json(['message'=>'Session not payable'], 422);
            }

            // سعر الجلسة (من SingleSessionOffer أو من therapist->price_cents)
            $sessionFee = (int) ($session->therapist->price_cents ?? 0);
            $serviceFee = (int) config('fees.single_session_service_cents', 0);
            $amount     = $sessionFee + $serviceFee;

            $therapistId   = $session->therapist_id;
            $therapySessionId = $session->id;
            $userPackageId = null;

            $payload = [
                'session_fee_cents'  => $sessionFee,
                'service_fee_cents'  => $serviceFee,
                'session_id'         => $session->id,
                'therapist_id'       => $session->therapist_id,
                'user_id'            => $user->id,
            ];
        } else {
            // شراء Package
            $package = Package::where('is_active', true)->findOrFail($data['id']);

            $therapistId = $package->created_by_therapist_id; // ممكن يبقى null لو Global

            $basePrice = (int) $package->price_cents;
            $discount  = (float) ($package->discount_percent ?? 0);
            $payable   = (int) round($basePrice * (100 - $discount) / 100);

            $serviceFee = (int) config('fees.package_service_cents', 0);
            $amount     = $payable + $serviceFee;

            $therapySessionId = null;
            $userPackageId    = null;

            $payload = [
                'package_id'          => $package->id,
                'base_price_cents'    => $basePrice,
                'discount_percent'    => $discount,
                'payable_cents'       => $payable,
                'service_fee_cents'   => $serviceFee,
                'sessions_count'      => $package->sessions_count,
                'session_duration_min'=> $package->session_duration_min,
                'validity_days'       => $package->validity_days,
                'user_id'             => $user->id,
                'therapist_id'        => $therapistId,
            ];
        }

        // ========== 2) نعمل Payment فى قاعدة البيانات ==========
        $reference = strtoupper(Str::random(10));

        $payment = DB::transaction(function () use (
            $user,
            $therapistId,
            $therapySessionId,
            $userPackageId,
            $data,
            $amount,
            $reference,
            $payload
        ) {
            return Payment::create([
                'user_id'            => $user->id,
                'therapist_id'       => $therapistId,
                'therapy_session_id' => $therapySessionId,
                'user_package_id'    => $userPackageId,
                'purpose'            => $data['purpose'],
                'amount_cents'       => $amount,
                'currency'           => 'EGP',
                'provider'           => 'paymob',
                'status'             => Payment::STATUS_PENDING,
                'reference'          => $reference,
                'payload'            => $payload,
            ]);
        });

        // ========== 3) Paymob: auth → order → payment_key ==========
        $token  = $paymob->auth();
        $order  = $paymob->createOrder($token, $amount, $payment->reference, []);
        $orderId = $order['id'] ?? null;

        $billing = array_merge([
            'first_name'   => $user->name ?? 'User',
            'last_name'    => 'Customer',
            'email'        => $user->email,
            'phone_number' => $user->phone ?? 'NA',
            'apartment'    => 'NA',
            'floor'        => 'NA',
            'street'       => 'NA',
            'building'     => 'NA',
            'shipping_method' => 'NA',
            'postal_code'  => '00000',
            'city'         => 'Cairo',
            'country'      => 'EG',
            'state'        => 'Cairo',
        ], $data['billing']);

        $pk = $paymob->paymentKey($token, $amount, $orderId, $billing);

        $payment->update([
            'provider_order_id' => (string) $orderId,
        ]);

        return response()->json([
            'message' => 'Payment initiated',
            'data' => [
                'payment_id'   => $payment->id,
                'reference'    => $payment->reference,
                'amount_cents' => $payment->amount_cents,
                'currency'     => $payment->currency,
                'purpose'      => $payment->purpose,
                'paymob'       => [
                    'order_id'    => $orderId,
                    'payment_key' => $pk['token'] ?? null,
                    'iframe_url'  => $pk['token'] ? $paymob->iframeUrl($pk['token']) : null,
                ],
            ],
        ], 201);
    }

    /**
     * Webhook حقيقى من Paymob بعد الدفع
     * Route: POST /payments/paymob/webhook
     */
    public function webhook(Request $r, PaymobService $paymob)
    {
        $hmac = $r->query('hmac');
        $data = $r->all();

        if (!$paymob->verifyHmac($data, $hmac)) {
            return response()->json(['message' => 'Invalid HMAC'], 403);
        }

        // حسب docs: merchant_order_id هو الـ reference اللى بعته
        $merchantOrderId = data_get($data, 'order.merchant_order_id');
        $txnId           = (string) data_get($data, 'id');
        $success         = (bool) data_get($data, 'success');

        /** @var Payment|null $payment */
        $payment = Payment::where('reference', $merchantOrderId)->first();
        if (!$payment) {
            return response()->json(['message'=>'Payment not found'], 404);
        }

        $update = [
            'provider_transaction_id' => $txnId,
            'payload'                 => $data, // بنخزن الرد الكامل لو حابة ترجعى له
        ];

        if ($success) {
            $update['status']  = Payment::STATUS_PAID;
            $update['paid_at'] = now();
        } else {
            $update['status']   = Payment::STATUS_FAILED;
            $update['failed_at'] = now();
        }

        DB::transaction(function () use ($payment, $update) {
            $payment->update($update);
            $this->applyBusinessLogicAfterPayment($payment);
        });

        return response()->json(['message' => 'Webhook processed']);
    }

    /**
     * 🔧 Fake success للـ local:
     * POST /payments/{payment}/fake-success
     */
    public function fakeSuccess(Request $r, Payment $payment)
    {
        // بس للـ local عشان ماحدش يلعب فى الـ prod
        if (!app()->environment('local')) {
            return response()->json(['message' => 'Fake success allowed only in local env'], 403);
        }

        DB::transaction(function () use ($payment) {
            $payment->update([
                'status'     => Payment::STATUS_PAID,
                'paid_at'    => now(),
                'payload'    => array_merge($payment->payload ?? [], [
                    'fake_success' => true,
                ]),
            ]);

            $this->applyBusinessLogicAfterPayment($payment);
        });

        return response()->json([
            'message' => 'Payment marked as PAID (fake) and business logic applied',
        ]);
    }

    /**
     * اللوچك اللى بيحصل بعد ما الدفع يبقى PAID
     * - لو single_session → session.status = confirmed
     * - لو package → ننشئ UserPackage (لو لسه) أو نحدّث الموجود
     */
    protected function applyBusinessLogicAfterPayment(Payment $payment): void
    {
        // Single session
        if ($payment->purpose === Payment::PURPOSE_SINGLE_SESSION && $payment->therapy_session_id) {
            $session = TherapySession::find($payment->therapy_session_id);
            if ($session && $payment->status === Payment::STATUS_PAID) {
                $session->update([
                    'status' => TherapySession::STATUS_CONFIRMED,
                ]);
            }
            return;
        }

        // Package
        if ($payment->purpose === Payment::PURPOSE_PACKAGE && $payment->status === Payment::STATUS_PAID) {

            // لو كان عندك بالفعل user_package_id على الـ payment سيبيه زى ما هو
            if ($payment->user_package_id) {
                return;
            }

            $payData   = $payment->payload ?? [];
            $packageId = $payData['package_id'] ?? null;

            if (!$packageId) {
                return;
            }

            /** @var Package|null $package */
            $package = Package::find($packageId);
            if (!$package) return;

            $sessionsCount = $package->sessions_count;
            $validityDays  = $package->validity_days;

            $userPackage = UserPackage::create([
                'user_id'        => $payment->user_id,
                'package_id'     => $package->id,
                'therapist_id'   => $payment->therapist_id,
                'sessions_total' => $sessionsCount,
                'sessions_used'  => 0,
                'status'         => 'active',
                'purchased_at'   => now(),
                'expires_at'     => $validityDays ? now()->addDays($validityDays) : null,
            ]);

            $payment->update([
                'user_package_id' => $userPackage->id,
            ]);
        }
    }
}
