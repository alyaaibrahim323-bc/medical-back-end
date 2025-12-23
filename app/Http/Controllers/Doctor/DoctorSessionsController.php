<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\TherapySession;
use App\Services\NotificationService;
use App\Services\ZoomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Mail\SessionLinkMail;
use Illuminate\Support\Facades\Mail;



class DoctorSessionsController extends Controller
{
    
    public function index(Request $r)
{
    $therapistId = $r->user()->therapist->id;

    $base = TherapySession::query()
        ->forDoctor($therapistId);

    $applyFiltersForCounts = function ($q) use ($r) {

        if ($scope = $r->query('scope')) {
            if ($scope === 'upcoming') {
                $q->where('scheduled_at', '>=', now());
            } elseif ($scope === 'past') {
                $q->where('scheduled_at', '<', now());
            }
        }

        if ($search = $r->query('search')) {
            $search = trim($search);
            $q->whereHas('user', function ($u) use ($search) {
                $u->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($r->filled('from')) {
            $q->whereDate('scheduled_at', '>=', $r->query('from'));
        }
        if ($r->filled('to')) {
            $q->whereDate('scheduled_at', '<=', $r->query('to'));
        }
    };

    $countsBase = clone $base;
    $applyFiltersForCounts($countsBase);

    $counts = [
        'all'        => (clone $countsBase)->count(),
        'past'       => (clone $countsBase)->where('scheduled_at', '<',  now())->count(),
        'pending'    => (clone $countsBase)->where('status', TherapySession::STATUS_PENDING)->count(),
        'confirmed'  => (clone $countsBase)->where('status', TherapySession::STATUS_CONFIRMED)->count(),
        'completed'  => (clone $countsBase)->where('status', TherapySession::STATUS_COMPLETED)->count(),
        'cancelled'  => (clone $countsBase)->where('status', TherapySession::STATUS_CANCELLED)->count(),
        'no_show'    => (clone $countsBase)->where('status', TherapySession::STATUS_NO_SHOW)->count(),
    ];

    $q = TherapySession::with(['user','payment'])
        ->forDoctor($therapistId);

    $applyFiltersForCounts($q);

    if ($r->filled('status') && in_array($r->status, [
        TherapySession::STATUS_PENDING,
        TherapySession::STATUS_CONFIRMED,
        TherapySession::STATUS_COMPLETED,
        TherapySession::STATUS_CANCELLED,
        TherapySession::STATUS_NO_SHOW,
    ], true)) {
        $q->where('status', $r->status);
    }

    $q->orderBy('scheduled_at', 'desc');

    return response()->json([
        'data'   => $q->paginate(20),
        'counts' => $counts,
    ]);
}


   
public function show(Request $r, $id)
{
    $therapistId = $r->user()->therapist->id;

    $s = TherapySession::with([
            'user',          
            'payment',
            'therapist',
            'therapist.user',
        ])
        ->where('therapist_id', $therapistId)
        ->findOrFail($id);

    $successfulSessionsCount = TherapySession::where('therapist_id', $therapistId)
        ->where('status', TherapySession::STATUS_COMPLETED)
        ->count();

    $sessionPriceCents = null;
    $sessionCurrency   = null;

    if ($s->payment) {
        $payload = $s->payment->payload ?? [];
        $sessionPriceCents = $payload['session_fee_cents'] ?? $s->payment->amount_cents;
        $sessionCurrency   = $s->payment->currency;
    } else {
        $sessionPriceCents = $s->therapist->price_cents ?? null;
        $sessionCurrency   = $s->therapist->currency ?? 'EGP';
    }

    return response()->json([
        'data' => [
            'id'           => $s->id,
            'status'       => $s->status,
            'scheduled_at' => $s->scheduled_at,
            'duration_min' => $s->duration_min,

            'client' => [
                'id'     => $s->user->id,
                'name'   => $s->user->name,
                'email'  => $s->user->email,
                'avatar' => $s->user->avatar,
                'phone'  => $s->user->phone,
            ],

            'therapist' => [
                'id'                       => $s->therapist->id,
                'name'                     => $s->therapist->user->name,
                'email'                    => $s->therapist->user->email,
                'avatar'                   => $s->therapist->user->avatar,
                'successful_sessions_count'=> $successfulSessionsCount,
                'session_price_cents'      => $sessionPriceCents,
                'session_currency'         => $sessionCurrency,
            ],

            'payment' => $s->payment ? [
                'id'           => $s->payment->id,
                'amount_cents' => $s->payment->amount_cents,
                'currency'     => $s->payment->currency,
                'status'       => $s->payment->status,
                'provider'     => $s->payment->provider, 
            ] : null,
        ],
    ]);
}



    public function updateStatus(Request $r, $id, NotificationService $notifications)
    {
        $therapistId = $r->user()->therapist->id;

        /** @var TherapySession $session */
        $session = TherapySession::where('therapist_id', $therapistId)->findOrFail($id);

        $data = $r->validate([
            'status' => ['required','in:confirmed,completed,cancelled,no_show'],
        ]);

        $newStatus = $data['status'];

        $session->update(['status' => $newStatus]);

        if ($newStatus === TherapySession::STATUS_COMPLETED) {
            $notifications->sendToUser(
                $session->user_id,
                'session_rating',
                [
                    'doctor'     => $session->therapist->user->name,
                    'session_id' => $session->id,
                ]
            );
        }

        return response()->json(['data' => $session->refresh()]);
    }

  
    public function createZoom(Request $r, int $id, ZoomService $zoom)
    {
        $therapistId = $r->user()->therapist->id;

        $s = TherapySession::where('therapist_id', $therapistId)->findOrFail($id);

        if ($s->status !== TherapySession::STATUS_CONFIRMED) {
            return response()->json(['message'=>'Session must be confirmed first.'], 422);
        }

        if ($s->zoom_join_url) {
            return response()->json([
                'message'=>'Zoom meeting already exists.',
                'data'=>[
                    'session_id'=>$s->id,
                    'zoom_meeting_id'=>$s->zoom_meeting_id,
                    'join_url'=>$s->zoom_join_url,
                    'start_url'=>$s->zoom_start_url,
                ]
            ], 409);
        }

        try {
            $meeting = $zoom->createMeeting(
                'Therapy Session #'.$s->id,
                $s->scheduled_at->toIso8601String(),
                (int)$s->duration_min
            );

            DB::transaction(function () use ($s, $meeting) {
                $s->update([
                    'zoom_meeting_id' => $meeting['id'] ?? null,
                    'zoom_join_url'   => $meeting['join_url'] ?? null,
                    'zoom_start_url'  => $meeting['start_url'] ?? null,
                ]);
            });

            Mail::to($s->user->email)->send(new SessionLinkMail($s, $s->zoom_join_url));

            app(NotificationService::class)->sendToUser(
                $s->user_id,
                'session_link_ready',
                [
                    'session_id' => $s->id,
                    'message' => 'تم إرسال لينك الجلسة إلى بريدك الإلكتروني. برجاء فحص الإيميل.',
                ]
            );

            return response()->json([
                'message'=>'Zoom meeting created & user notified.',
                'data'=>[
                    'session_id'=>$s->id,
                    'zoom_meeting_id'=>$meeting['id'] ?? null,
                    'join_url'=>$meeting['join_url'] ?? null,
                    'start_url'=>$meeting['start_url'] ?? null,
                ]
            ], 201);

        } catch (Throwable $e) {
            return response()->json(['message'=>'Failed to create Zoom meeting.','error'=>$e->getMessage()], 502);
        }
    }

  
    public function addSessionLink(Request $r, int $id)
    {
        $data = $r->validate([
            'zoom_join_url'  => ['required','url','max:255'],
            'zoom_start_url' => ['nullable','url','max:255'],
        ]);

        $therapistId = $r->user()->therapist->id;

        $s = TherapySession::where('therapist_id', $therapistId)->findOrFail($id);

        if ($s->status !== TherapySession::STATUS_CONFIRMED) {
            return response()->json(['message'=>'Session must be confirmed first.'], 422);
        }

        $s->update($data);

        Mail::to($s->user->email)->send(new SessionLinkMail($s, $s->zoom_join_url));

        app(NotificationService::class)->sendToUser(
            $s->user_id,
            'session_link_ready',
            [
                'session_id' => $s->id,
                'message' => 'تم إرسال لينك الجلسة إلى بريدك الإلكتروني. برجاء فحص الإيميل.',
            ]
        );

        return response()->json([
            'message'=>'Session link saved & user notified.',
            'data'=>[
                'session_id'=>$s->id,
                'join_url'=>$s->zoom_join_url,
                'start_url'=>$s->zoom_start_url,
            ]
        ]);
    }
}
