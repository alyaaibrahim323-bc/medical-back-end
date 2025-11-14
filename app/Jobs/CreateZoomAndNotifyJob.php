<?php

namespace App\Jobs;

use App\Models\TherapySession;
use App\Services\ZoomService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class CreateZoomAndNotifyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $therapySessionId) {}

    public function handle(ZoomService $zoom): void
    {
        $s = TherapySession::with(['user','therapist.user'])->findOrFail($this->therapySessionId);
        if ($s->status !== TherapySession::STATUS_CONFIRMED || $s->zoom_join_url) return;

        $m = $zoom->createMeeting('Therapy Session #'.$s->id, $s->scheduled_at->toIso8601String(), $s->duration_min);
        $s->update([
            'zoom_meeting_id'=>$m['id']??null,
            'zoom_join_url'=>$m['join_url']??null,
            'zoom_start_url'=>$m['start_url']??null
        ]);

        Mail::raw("Your session #{$s->id}\nJoin: {$s->zoom_join_url}", fn($msg)=>$msg->to($s->user->email)->subject('Session Confirmed'));
        Mail::raw("Start session #{$s->id}\nStart: {$s->zoom_start_url}", fn($msg)=>$msg->to($s->therapist->user->email)->subject('New Session'));
    }
}
