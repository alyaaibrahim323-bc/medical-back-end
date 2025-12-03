<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\Package;
use App\Models\UserPackage;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\NotificationService;

class PaymentsController extends Controller
{
   /**
     * POST api/payments
     * body:
     *   purpose: single_session | package
     *   id:      therapy_session_id OR package_id
     *
     * مثال:
     *  {
     *    "purpose": "single_session",
     *    "id": 12
     *  }
     *
     *  {
     *    "purpose": "package",
     *    "id": 3
     *  }
     */
    public function create(Request $r, PaymobService $paymob)
    {
        $data = $r->validate([
            'purpose' => ['required','in:single_session,package'],
            'id'      => ['required','integer'],
            // 👈 مفيش billing فى الـ request
        ]);

        $user = $r->user();

        // =============================
        // 1) حدد الهدف والمبلغ + payload
        // =============================
        $therapistId      = null;
        $therapySessionId = null;
        $userPackageId    = null;
        $amount           = 0;
        $payload          = [];

        if ($data['purpose'] === Payment::PURPOSE_SINGLE_SESSION) {
            // الجلسة اللى اتعملت قبل كده فى TherapySessionController
            $session = TherapySession::with('therapist')
                ->where('user_id', $user->id)
                ->findOrFail($data['id']);

            if ($session->status !== TherapySession::STATUS_PENDING) {
                return response()->json(['message'=>'Session not payable'], 422);
            }

            $sessionFee = (int) ($session->therapist->price_cents ?? 0);
            $serviceFee = (int) config('fees.single_session_service_cents', 0);
            $amount     = $sessionFee + $serviceFee;

            $therapistId      = $session->therapist_id;
            $therapySessionId = $session->id;

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

            $therapistId = $package->created_by_therapist_id;

            $basePrice = (int) $package->price_cents;
            $discount  = (float) ($package->discount_percent ?? 0);
            $payable   = (int) round($basePrice * (100 - $discount) / 100);

            $serviceFee = (int) config('fees.package_service_cents', 0);
            $amount     = $payable + $serviceFee;

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

        // =============================
        // 2) MOCK MODE → نجاح فورى بدون Paymob
        // =============================
        if (config('payments.mock')) {
            $reference = strtoupper(Str::random(10));

            /** @var Payment $payment */
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
                    // ⭐ خلى القيمة دى تماتش الـ ENUM عندك
                    'provider'           => 'paymob', // أو خلى العمود يقبل 'mock'
                    'status'             => Payment::STATUS_PAID,
                    'reference'          => $reference,
                    'paid_at'            => now(),
                    'payload'            => array_merge($payload, [
                        'mock' => true,
                    ]),
                ]);
            });

            // نطبق البزنس لوجك بعد الدفع
            $this->applyBusinessLogicAfterPayment($payment);

            return response()->json([
                'message' => 'Mock payment success (no Paymob call).',
                'data' => [
                    'payment_id'   => $payment->id,
                    'reference'    => $payment->reference,
                    'amount_cents' => $payment->amount_cents,
                    'currency'     => $payment->currency,
                    'purpose'      => $payment->purpose,
                    'paymob'       => null,
                ],
            ], 201);
        }

        // =============================
        // 3) السيناريو الحقيقى مع Paymob
        //    (من غير ما الفرونت يبعت billing)
        // =============================
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

        // ⭐ نكوّن الـ billing جوه الباك إند من بيانات اليوزر
        $billing = [
            'first_name'      => $user->name ?? 'User',
            'last_name'       => 'Customer',
            'email'           => $user->email,
            'phone_number'    => $user->phone ?? 'NA',
            'apartment'       => 'NA',
            'floor'           => 'NA',
            'street'          => 'NA',
            'building'        => 'NA',
            'shipping_method' => 'NA',
            'postal_code'     => '00000',
            'city'            => 'Cairo',
            'country'         => 'EG',
            'state'           => 'Cairo',
        ];

        // Paymob: auth → order → payment_key
        $token   = $paymob->auth();
        $order   = $paymob->createOrder($token, $amount, $payment->reference, []);
        $orderId = $order['id'] ?? null;

        $pk = $paymob->paymentKey($token, $amount, $orderId, $billing);

        $payment->update([
            'provider_order_id' => (string) $orderId,
            'payload'           => array_merge($payment->payload ?? [], [
                'billing' => $billing,
            ]),
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
protected function applyBusinessLogicAfterPayment(Payment $payment): void
{
    /** @var NotificationService $notifications */
    $notifications = app(NotificationService::class);

    // 1) Single Session
    if ($payment->purpose === Payment::PURPOSE_SINGLE_SESSION && $payment->therapy_session_id) {
        $session = TherapySession::with('therapist.user')->find($payment->therapy_session_id);

        if ($session && $payment->status === Payment::STATUS_PAID) {
            $session->update([
                'status' => TherapySession::STATUS_CONFIRMED,
            ]);

            $notifications->sendToUser(
                $payment->user_id,
                'payment_success',
                [
                    'amount' => $payment->amount_cents / 100,
                    'item'   => 'Single session with ' . ($session->therapist->user->name ?? 'therapist'),
                ]
            );

            $notifications->sendToUser(
                $payment->user_id,
                'session_upcoming',
                [
                    'doctor' => $session->therapist->user->name ?? 'therapist',
                    'time'   => $session->scheduled_at->format('g:i A'),
                ]
            );
        }

        if ($session && $payment->status === Payment::STATUS_FAILED) {
            $notifications->sendToUser(
                $payment->user_id,
                'payment_failed',
                [
                    'amount' => $payment->amount_cents / 100,
                    'item'   => 'Single session',
                ]
            );
        }

        return;
    }

    // 2) Package
    if ($payment->purpose === Payment::PURPOSE_PACKAGE) {

        if ($payment->status === Payment::STATUS_FAILED) {
            $notifications->sendToUser(
                $payment->user_id,
                'payment_failed',
                [
                    'amount' => $payment->amount_cents / 100,
                    'item'   => 'Package',
                ]
            );
            return;
        }

        if ($payment->status !== Payment::STATUS_PAID) {
            return;
        }

        // لو عندك user_package_id موجود
        if ($payment->user_package_id) {
            $notifications->sendToUser(
                $payment->user_id,
                'payment_success',
                [
                    'amount' => $payment->amount_cents / 100,
                    'item'   => 'Package',
                ]
            );
            return;
        }

        $payData   = $payment->payload ?? [];
        $packageId = $payData['package_id'] ?? null;

        if (!$packageId) {
            return;
        }

        /** @var Package|null $package */
        $package = Package::find($packageId);
        if (!$package) {
            return;
        }

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

        // 👇 هنا التعديل المهم
        // name ممكن تكون JSON (array) أو string
        $rawName = $package->name;

        if (is_array($rawName)) {
            // لو متخزنة JSON
            $nameEn = $rawName['en'] ?? null;
            $nameAr = $rawName['ar'] ?? null;
            $name   = $nameEn ?? $nameAr ?? reset($rawName);
        } else {
            $name = $rawName;
        }

        $itemLabel = trim('Package ' . ($name ?? ''));

        $notifications->sendToUser(
            $payment->user_id,
            'payment_success',
            [
                'amount' => $payment->amount_cents / 100,
                'item'   => $itemLabel,
            ]
        );
    }
}



    // ... باقى الكنترولر زى ما هو (webhook, fakeSuccess, applyBusinessLogicAfterPayment ...)
}
