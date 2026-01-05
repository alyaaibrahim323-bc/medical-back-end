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
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TherapySessionController extends Controller
{

    public function index(Request $r)
    {
        $user = $r->user();

        $q = TherapySession::with(['therapist.user'])
            ->where('user_id', $user->id)
            ->orderByDesc('scheduled_at');

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


    public function show(Request $r, $id)
    {
        $user = $r->user();

        $s = TherapySession::with(['therapist.user', 'userPackage.package'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json(['data' => $s]);
    }


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

        $durationMin = $this->resolveDurationMinutes($therapist, $data, $user);

        $slotStart = $scheduledAt->copy();
        $slotEnd   = $scheduledAt->copy()->addMinutes($durationMin);

        if (! $availability->isSlotFree($therapist->id, $slotStart, $slotEnd)) {
            return response()->json(['message' => 'Time slot is no longer available'], 422);
        }

        if ($data['billing_type'] === 'single') {
            return $this->createSingleSessionWithPayment(
                user: $user,
                therapist: $therapist,
                scheduledAt: $scheduledAt,
                durationMin: $durationMin
            );
        }

        return $this->createSessionFromPackage(
            user: $user,
            therapist: $therapist,
            scheduledAt: $scheduledAt,
            durationMin: $durationMin,
            userPackageId: $data['user_package_id'] ?? null
        );
    }


    public function cancel(Request $r, $id)
    {
        $user = $r->user();

        $s = TherapySession::where('user_id', $user->id)->findOrFail($id);

        if (in_array($s->status, [
            TherapySession::STATUS_COMPLETED,
            TherapySession::STATUS_NO_SHOW,
        ], true)) {
            return response()->json(['message' => 'Cannot cancel this session'], 422);
        }

        $s->update([
            'status' => TherapySession::STATUS_CANCELLED,
        ]);

        return response()->json(['message' => 'Session cancelled']);
    }


   protected function createSingleSessionWithPayment($user, Therapist $therapist, Carbon $scheduledAt, int $durationMin)
{
    $offer = SingleSessionOffer::where('therapist_id', $therapist->id)
        ->where('is_active', true)
        ->first();

    $sessionFee = $offer
        ? (int) $offer->price_cents
        : (int) ($therapist->price_cents ?? 0);

    $currency = $offer
        ? ($offer->currency ?? 'EGP')
        : ($therapist->currency ?? 'EGP');

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
        $session = TherapySession::create([
            'user_id'      => $user->id,
            'therapist_id' => $therapist->id,
            'scheduled_at' => $scheduledAt,
            'duration_min' => $durationMin,
            'status'       => TherapySession::STATUS_PENDING, // pending_payment
            'billing_type' => 'single',
        ]);

        $payment = Payment::create([
            'user_id'            => $user->id,
            'therapist_id'       => $therapist->id,
            'therapy_session_id' => $session->id,
            'user_package_id'    => null,
            'purpose'            => Payment::PURPOSE_SINGLE_SESSION,
            'amount_cents'       => $total,
            'currency'           => $currency,
            'provider'           => 'kashier', // ✅ بدل paymob
            'status'             => Payment::STATUS_PENDING,
            'reference'          => 'SS-' . Str::uuid(),
            'payload'            => [
                'session_fee_cents' => $sessionFee,
                'service_fee_cents' => $serviceFee,
                'duration_min'      => $durationMin,
                'therapist_id'      => $therapist->id,
                'user_id'           => $user->id,
            ],
        ]);
    });

    return response()->json([
        'data' => [
            'session' => $session->load('therapist.user'),
            'payment' => [
                'id'                => $payment->id,
                'amount_cents'      => $payment->amount_cents,
                'currency'          => $payment->currency,
                'session_fee_cents' => $sessionFee,
                'service_fee_cents' => $serviceFee,
                'reference'         => $payment->reference,
            ],
            'billing_source' => 'single',
        ]
    ], 201);
}


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

        if ($userPackage->therapist_id && $userPackage->therapist_id !== $therapist->id) {
            return response()->json(['message' => 'Package does not belong to this therapist'], 422);
        }

        if ($userPackage->sessions_used >= $userPackage->sessions_total) {
            return response()->json(['message' => 'No remaining sessions in this package'], 422);
        }

        if ($userPackage->expires_at && $userPackage->expires_at->isPast()) {
            return response()->json(['message' => 'Package has expired'], 422);
        }

        $session = null;

        $hasActiveSession = TherapySession::query()
        ->where('user_id', $user->id)
        ->where('user_package_id', $userPackage->id)
        ->whereIn('status', [
            TherapySession::STATUS_PENDING,
            TherapySession::STATUS_CONFIRMED,
        ])
        ->exists();

    if ($hasActiveSession) {
        return response()->json([
            'message' => 'You already have an active session in this package. Complete it before booking another one.'
        ], 422);
}


        DB::transaction(function () use (
            $user,
            $therapist,
            $scheduledAt,
            $durationMin,
            $userPackage,
            &$session
            ) {
            $session = TherapySession::create([
                'user_id'        => $user->id,
                'therapist_id'   => $therapist->id,
                'scheduled_at'   => $scheduledAt,
                'duration_min'   => $durationMin,
                'status'         => TherapySession::STATUS_CONFIRMED,
                'billing_type'   => 'package',
                'user_package_id'=> $userPackage->id,
            ]);

            PackageRedemption::create([
                'user_package_id'    => $userPackage->id,
                'therapy_session_id' => $session->id,
                'redeemed_at'        => now(),
                'notes'              => null,
            ]);


        });

        /** @var \App\Models\TherapySession $session */

        app(NotificationService::class)->sendToUser(
            $session->user_id,
            'session upcoming',
            [
                'doctor' => $session->therapist->user->name,
                'time'   => $session->scheduled_at->format('g:i A'),
            ]
        );

        return response()->json([
            'data' => [
                'session'        => $session->load('therapist.user', 'userPackage.package'),
                'billing_source' => 'package',
            ]
        ], 201);
    }

    protected function resolveDurationMinutes(Therapist $therapist, array $data, $user): int
    {
        if (($data['billing_type'] ?? null) === 'package' && !empty($data['user_package_id'])) {
            $up = UserPackage::with('package')
                ->where('id', $data['user_package_id'])
                ->where('user_id', $user->id)
                ->first();

            if ($up && $up->package && $up->package->session_duration_min) {
                return (int) $up->package->session_duration_min;
            }
        }

        $offer = SingleSessionOffer::where('therapist_id', $therapist->id)
            ->where('is_active', true)
            ->first();

        if ($offer && $offer->duration_min) {
            return (int) $offer->duration_min;
        }

        if (! empty($therapist->default_session_duration_min)) {
            return (int) $therapist->default_session_duration_min;
        }

        return 60;
    }
}
