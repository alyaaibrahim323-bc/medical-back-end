<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Therapist;
use App\Models\TherapySession;
use App\Models\UserPackage;
use App\Models\PackageRedemption;
use App\Models\Payment;
use App\Models\SingleSessionOffer;
use App\Services\TherapistAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TherapySessionController extends Controller
{
    /**
     * قائمة جلسات اليوزر (upcoming / past)
     */
    public function index(Request $r)
    {
        $user = $r->user();

        $q = TherapySession::with(['therapist.user'])
            ->where('user_id', $user->id)
            ->orderByDesc('scheduled_at');

        // ?scope=upcoming | past | all
        $scope = $r->query('scope');

        if ($scope === 'upcoming') {
            $q->where('scheduled_at', '>=', now())
              ->whereIn('status', [
                  TherapySession::STATUS_PENDING,
                  TherapySession::STATUS_CONFIRMED,
              ]);
        } elseif ($scope === 'past') {
            $q->where('scheduled_at', '<', now());
        }

        return response()->json(['data' => $q->paginate(20)]);
    }

    /**
     * تفاصيل جلسة واحدة تخص اليوزر
     */
    public function show(Request $r, $id)
    {
        $user = $r->user();

        $s = TherapySession::with(['therapist.user', 'userPackage.package'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json(['data' => $s]);
    }

    /**
     * إنشاء جلسة:
     * - billing_type = single  → حجز جلسة + Payment
     * - billing_type = package → حجز جلسة من رصيد الباكدج
     */
    public function store(Request $r, TherapistAvailabilityService $availability)
    {
        $user = $r->user();

        $data = $r->validate([
            'therapist_id'    => ['required', 'exists:therapists,id'],
            'scheduled_at'    => ['required', 'date'],
            'billing_type'    => ['required', 'in:single,package'],
            'user_package_id' => ['nullable', 'integer', 'exists:user_packages,id'],
        ]);

        $therapist = Therapist::where('is_active', true)->findOrFail($data['therapist_id']);

        $scheduledAt = Carbon::parse($data['scheduled_at']);

        // تحديد مدة الجلسة حسب نوع الحجز
        $durationMin = $this->resolveDurationMinutes($therapist, $data, $user);

        $slotStart = $scheduledAt->copy();
        $slotEnd   = $scheduledAt->copy()->addMinutes($durationMin);

        // تأكد إن الـ slot فاضى (لا يوجد جلسة تانية فى نفس المعاد)
        if (! $availability->isSlotFree($therapist->id, $slotStart, $slotEnd)) {
            return response()->json(['message' => 'Time slot is no longer available'], 422);
        }

        if ($data['billing_type'] === 'single') {
            // 🟢 حجز single session + Payment
            return $this->createSingleSessionWithPayment(
                user: $user,
                therapist: $therapist,
                scheduledAt: $scheduledAt,
                durationMin: $durationMin
            );
        }

        // 🟣 حجز جلسة من Package
        return $this->createSessionFromPackage(
            user: $user,
            therapist: $therapist,
            scheduledAt: $scheduledAt,
            durationMin: $durationMin,
            userPackageId: $data['user_package_id'] ?? null
        );
    }

    /**
     * إلغاء جلسة من ناحية اليوزر
     */
    public function cancel(Request $r, $id)
    {
        $user = $r->user();

        $s = TherapySession::where('user_id', $user->id)->findOrFail($id);

        // ممكن تضيفي validation زيادة (مثلاً لا يلغى جلسة ماضية، أو قبل X ساعات)
        if (in_array($s->status, [
            TherapySession::STATUS_COMPLETED,
            TherapySession::STATUS_NO_SHOW,
        ])) {
            return response()->json(['message' => 'Cannot cancel this session'], 422);
        }

        $s->update([
            'status' => TherapySession::STATUS_CANCELLED,
        ]);

        return response()->json(['message' => 'Session cancelled']);
    }

    /* =========================================================
     *           HELPERS: SINGLE SESSION & PACKAGE
     * ========================================================= */

    /**
     * حجز single session + إنشاء Payment pending
     *
     * هنا بنحافظ على نفس الـ structure اللى يناسب شاشة الدفع:
     *  - session_fee_cents
     *  - service_fee_cents
     *  - amount_cents (total)
     */
    protected function createSingleSessionWithPayment($user, Therapist $therapist, Carbon $scheduledAt, int $durationMin)
    {
        // نجيب عرض السينجل سيشن لو موجود
        $offer = SingleSessionOffer::where('therapist_id', $therapist->id)
            ->where('is_active', true)
            ->first();

        // سعر الجلسة
        $sessionFee = $offer
            ? (int) $offer->price_cents
            : (int) ($therapist->price_cents ?? 0);

        $currency = $offer
            ? ($offer->currency ?? 'EGP')
            : ($therapist->currency ?? 'EGP');

        // service fee من config (أو 0)
        $serviceFee = (int) config('fees.single_session_service_cents', 0);

        $total = $sessionFee + $serviceFee;

        $session = null;
        $payment = null;

        DB::transaction(function () use (
            $user,
            $therapist,
            $scheduledAt,
            $durationMin,
            $sessionFee,
            $serviceFee,
            $total,
            $currency,
            &$session,
            &$payment
        ) {
            // 1) إنشاء الجلسة بحالة pending_payment
            $session = TherapySession::create([
                'user_id'      => $user->id,
                'therapist_id' => $therapist->id,
                'scheduled_at' => $scheduledAt,
                'duration_min' => $durationMin,
                'status'       => TherapySession::STATUS_PENDING,
                'billing_type' => 'single',
            ]);

            // 2) إنشاء Payment
            $payment = Payment::create([
                'user_id'           => $user->id,
                'therapist_id'      => $therapist->id,
                'therapy_session_id'=> $session->id,
                'user_package_id'   => null,
                'purpose'           => 'single_session',
                'amount_cents'      => $total,
                'currency'          => $currency,
                'provider'          => 'paymob',
                'status'            => 'pending',
                'reference'         => 'SS-'.Str::uuid(),
                'payload'           => [
                    'session_fee_cents'   => $sessionFee,
                    'service_fee_cents'   => $serviceFee,
                    'duration_min'        => $durationMin,
                    'therapist_id'        => $therapist->id,
                    'user_id'             => $user->id,
                ],
            ]);
        });

        return response()->json([
            'data' => [
                'session' => $session->load('therapist.user'),
                'payment' => [
                    'id'                 => $payment->id,
                    'amount_cents'       => $payment->amount_cents,
                    'currency'           => $payment->currency,
                    'session_fee_cents'  => $sessionFee,
                    'service_fee_cents'  => $serviceFee,
                    'reference'          => $payment->reference,
                ],
                'billing_source' => 'single',
            ]
        ], 201);
    }

    /**
     * حجز جلسة من Package (بدون دفع، من رصيد الباكدج)
     */
    protected function createSessionFromPackage(
        $user,
        Therapist $therapist,
        Carbon $scheduledAt,
        int $durationMin,
        ?int $userPackageId
    ) {
        if (! $userPackageId) {
            return response()->json([
                'message' => 'user_package_id is required for package billing'
            ], 422);
        }

        $userPackage = UserPackage::where('id', $userPackageId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        // لو الباكيدج مربوطة بدكتور معيّن، تأكد إن نفس الدكتور
        if ($userPackage->therapist_id && $userPackage->therapist_id !== $therapist->id) {
            return response()->json(['message' => 'Package does not belong to this therapist'], 422);
        }

        // تأكد من رصيد الجلسات
        if ($userPackage->sessions_used >= $userPackage->sessions_total) {
            return response()->json(['message' => 'No remaining sessions in this package'], 422);
        }

        // تأكد من الصلاحية (expiry)
        if ($userPackage->expires_at && $userPackage->expires_at->isPast()) {
            return response()->json(['message' => 'Package has expired'], 422);
        }

        $session = null;

        DB::transaction(function () use ($user, $therapist, $scheduledAt, $durationMin, $userPackage, &$session) {

            // 1) نخلق Session بحالة confirmed + billing_type=package
            $session = TherapySession::create([
                'user_id'        => $user->id,
                'therapist_id'   => $therapist->id,
                'scheduled_at'   => $scheduledAt,
                'duration_min'   => $durationMin,
                'status'         => TherapySession::STATUS_CONFIRMED,
                'billing_type'   => 'package',
                'user_package_id'=> $userPackage->id,
            ]);

            // 2) نسجل Redemption فى package_redemptions
            PackageRedemption::create([
                'user_package_id'    => $userPackage->id,
                'therapy_session_id' => $session->id,
                'redeemed_at'        => now(),
                'notes'              => null,
            ]);

            // 3) نحدّث counters
            $userPackage->sessions_used += 1;

            if ($userPackage->sessions_used >= $userPackage->sessions_total) {
                $userPackage->status = 'completed'; // أو exhausted حسب ما مسمياه فى الجدول
            }

            $userPackage->save();
        });

        return response()->json([
            'data' => [
                'session'        => $session->load('therapist.user', 'userPackage.package'),
                'billing_source' => 'package',
            ]
        ], 201);
    }

    /**
     * تحديد مدة الجلسة:
     * - لو billing_type=package → من package.session_duration_min
     * - لو single → من SingleSessionOffer أو default
     */
    protected function resolveDurationMinutes(Therapist $therapist, array $data, $user): int
    {
        // لو من Package
        if (($data['billing_type'] ?? null) === 'package' && !empty($data['user_package_id'])) {
            $up = UserPackage::with('package')
                ->where('id', $data['user_package_id'])
                ->where('user_id', $user->id)
                ->first();

            if ($up && $up->package && $up->package->session_duration_min) {
                return (int) $up->package->session_duration_min;
            }
        }

        // لو single: نحاول نجيب من SingleSessionOffer
        $offer = SingleSessionOffer::where('therapist_id', $therapist->id)
            ->where('is_active', true)
            ->first();

        if ($offer && $offer->duration_min) {
            return (int) $offer->duration_min;
        }

        // fallback: لو عندك عمود فى therapists اسمه default_session_duration_min
        if (!empty($therapist->default_session_duration_min)) {
            return (int) $therapist->default_session_duration_min;
        }

        // fallback أخير
        return 60;
    }
}
