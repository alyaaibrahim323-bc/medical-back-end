<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Jobs\CreateZoomAndNotifyJob;
use App\Services\ZoomService;
use Throwable;
use Illuminate\Support\Facades\DB;
use App\Models\TherapySession;
use Illuminate\Http\Request;
use App\Services\NotificationService;


class DoctorSessionsController extends Controller
{
    public function index(Request $r)
    {
        $therapistId = auth()->user()->therapist->id;

        $scope = $r->query('scope');
        $q = TherapySession::with(['user','payment'])
            ->forDoctor($therapistId)
            ->when($scope==='upcoming', fn($x)=>$x->upcoming())
            ->when($scope==='past',     fn($x)=>$x->past())
            ->orderBy('scheduled_at','desc');

        // (اختياري) فلترة بالتاريخ
        if ($r->filled('from')) $q->where('scheduled_at','>=', $r->query('from'));
        if ($r->filled('to'))   $q->where('scheduled_at','<=', $r->query('to'));

        return response()->json(['data'=>$q->paginate(20)]);
    }

    public function show($id)
    {
        $therapistId = auth()->user()->therapist->id;
        $s = TherapySession::with(['user','payment'])
            ->where('therapist_id',$therapistId)
            ->findOrFail($id);

        return response()->json(['data'=>$s]);
    }

    public function updateStatus(Request $r, $id)
    {
        $therapistId = auth()->user()->therapist->id;
        $s = TherapySession::where('therapist_id',$therapistId)->findOrFail($id);

        $data = $r->validate([
            'status' => ['required','in:confirmed,completed,cancelled,no_show']
        ]);

        $s->update(['status'=>$data['status']]);
        if ($newStatus === TherapySession::STATUS_COMPLETED) {
    app(NotificationService::class)->sendToUser(
        $session->user_id,
        'session_rating',
        [
            'doctor' => $session->therapist->user->name,
            'session_id' => $session->id,
        ]
    );
}
        return response()->json(['data'=>$s->refresh()]);
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
                    'scheduled_at'=>$s->scheduled_at,
                    'duration_min'=>$s->duration_min,
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

            // (اختياري المرحلة 3) ابعت للمستخدم بالـ join_url:
            // Mail::to($s->user->email)->send(new SessionLinkMail($s, $s->zoom_join_url));

            return response()->json([
                'message'=>'Zoom meeting created successfully.',
                'data'=>[
                    'session_id'=>$s->id,
                    'zoom_meeting_id'=>$meeting['id'] ?? null,
                    'join_url'=>$meeting['join_url'] ?? null,
                    'start_url'=>$meeting['start_url'] ?? null,
                    'scheduled_at'=>$s->scheduled_at,
                    'duration_min'=>$s->duration_min,
                ]
            ], 201);

        } catch (Throwable $e) {
            return response()->json(['message'=>'Failed to create Zoom meeting.','error'=>$e->getMessage()], 502);
        }
    }

    // 2.2 إضافة لينك يدوي (كما في المودال بالصورة)
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

        // (اختياري المرحلة 3) ابعت للمستخدم بالـ join_url:
        // Mail::to($s->user->email)->send(new SessionLinkMail($s, $s->zoom_join_url));

        return response()->json([
            'message'=>'Session link saved.',
            'data'=>[
                'session_id'=>$s->id,
                'join_url'=>$s->zoom_join_url,
                'start_url'=>$s->zoom_start_url,
                'scheduled_at'=>$s->scheduled_at,
                'duration_min'=>$s->duration_min,
            ]
        ]);
    }
}
