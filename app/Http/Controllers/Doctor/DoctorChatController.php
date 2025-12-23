<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use Illuminate\Http\Request;

class DoctorChatController extends Controller
{
    public function index(Request $request)
    {
        $user        = $request->user();
        $therapist   = optional($user->therapist);
        $therapistId = $therapist->id;
        $doctorUserId = $user->id; 

        if (! $therapistId) {
            return response()->json([
                'message' => 'No therapist profile attached to this user.',
            ], 403);
        }

      
        $base = Chat::query()
            ->where('therapist_id', $therapistId);

       
        $allCount = (clone $base)->count();

       
        $pendingForDoctorCount = (clone $base)
            ->whereHas('messages', function ($q) {
                $q->where('sender_role', 'client');
            })
            ->whereDoesntHave('messages', function ($q) {
                $q->where('sender_role', 'therapist');
            })
            ->count();

        
        $repliedForDoctorCount = (clone $base)
            ->whereHas('messages', function ($q) {
                $q->where('sender_role', 'therapist');
            })
            ->count();

        $closedCount = (clone $base)
            ->where('status', 'closed')
            ->count();

        
        $unreadCount = (clone $base)
            ->whereHas('messages', function ($m) use ($doctorUserId) {
                $m->where('sender_role', 'client')
                  ->whereDoesntHave('reads', function ($r) use ($doctorUserId) {
                      $r->where('user_id', $doctorUserId);
                  });
            })
            ->count();

        $readCount = $allCount - $unreadCount;

        $counts = [
            'all'               => $allCount,
            'pending_for_doctor'=> $pendingForDoctorCount,
            'replied_for_doctor'=> $repliedForDoctorCount,
            'closed'            => $closedCount,
            'newest'            => $unreadCount, 
            'oldest'            => $readCount,   
        ];

       
        $q = Chat::with([
                'session',
                'user',             
                'therapist.user',   
                'lastMessage',    
            ])
            ->where('therapist_id', $therapistId);

        if ($tab = $request->query('tab')) {

            if ($tab === 'newest') {
               
                $q->whereHas('messages', function ($m) use ($doctorUserId) {
                    $m->where('sender_role', 'client')
                      ->whereDoesntHave('reads', function ($r) use ($doctorUserId) {
                          $r->where('user_id', $doctorUserId);
                      });
                });
            }

            if ($tab === 'oldest') {
                
                $q->whereDoesntHave('messages', function ($m) use ($doctorUserId) {
                    $m->where('sender_role', 'client')
                      ->whereDoesntHave('reads', function ($r) use ($doctorUserId) {
                          $r->where('user_id', $doctorUserId);
                      });
                });
            }

            if ($tab === 'pending_for_doctor') {
                $q->whereHas('messages', function ($m) {
                        $m->where('sender_role', 'client');
                    })
                  ->whereDoesntHave('messages', function ($m) {
                        $m->where('sender_role', 'therapist');
                    });
            }

            if ($tab === 'replied_for_doctor') {
                $q->whereHas('messages', function ($m) {
                    $m->where('sender_role', 'therapist');
                });
            }

            if ($tab === 'closed') {
                $q->where('status', 'closed');
            }
        }

 
        if ($search = $request->query('search')) {
            $q->whereHas('user', function ($u) use ($search) {
                $u->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $chats = $q->orderByDesc('last_message_at')->paginate(20);

        return ChatResource::collection($chats)->additional([
            'counts' => $counts,
        ]);
    }

    public function show(Request $request, Chat $chat)
    {
        $this->authorize('view', $chat);

        $chat->load(['session', 'user', 'therapist']);

        return new ChatResource($chat);
    }
}
