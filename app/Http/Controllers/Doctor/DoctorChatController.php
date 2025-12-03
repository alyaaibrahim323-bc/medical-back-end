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
        $doctorUserId = $user->id; // ده الـ user_id بتاع الدكتور فى chat_reads

        if (! $therapistId) {
            return response()->json([
                'message' => 'No therapist profile attached to this user.',
            ], 403);
        }

        // =========================
        // BASE QUERY لكل الشاتات
        // =========================
        $base = Chat::query()
            ->where('therapist_id', $therapistId);

        // ==============
        // COUNTS
        // ==============

        // كل الشاتس للدكتور
        $allCount = (clone $base)->count();

        // pending_for_doctor:
        // فيه message من client ومفيش ولا message من therapist
        $pendingForDoctorCount = (clone $base)
            ->whereHas('messages', function ($q) {
                $q->where('sender_role', 'client');
            })
            ->whereDoesntHave('messages', function ($q) {
                $q->where('sender_role', 'therapist');
            })
            ->count();

        // replied_for_doctor:
        // فيه على الأقل message واحدة من therapist
        $repliedForDoctorCount = (clone $base)
            ->whereHas('messages', function ($q) {
                $q->where('sender_role', 'therapist');
            })
            ->count();

        // closed (لو محتاجة فى الدكتور)
        $closedCount = (clone $base)
            ->where('status', 'closed')
            ->count();

        // unread (newest): شات فيه message من client
        // مفيهاش read record للدكتور
        $unreadCount = (clone $base)
            ->whereHas('messages', function ($m) use ($doctorUserId) {
                $m->where('sender_role', 'client')
                  ->whereDoesntHave('reads', function ($r) use ($doctorUserId) {
                      $r->where('user_id', $doctorUserId);
                  });
            })
            ->count();

        // read (oldest) = all - unread
        $readCount = $allCount - $unreadCount;

        $counts = [
            'all'               => $allCount,
            'pending_for_doctor'=> $pendingForDoctorCount,
            'replied_for_doctor'=> $repliedForDoctorCount,
            'closed'            => $closedCount,
            'newest'            => $unreadCount, // chats فيها رسائل جديدة من العميل
            'oldest'            => $readCount,   // مفيش رسائل جديدة
        ];

        // =========================
        // QUERY الأساسية للـ list
        // =========================
        $q = Chat::with([
                'session',
                'user',             // client
                'therapist.user',   // doctor info
                'lastMessage',      // لو موجودة فى الموديل
            ])
            ->where('therapist_id', $therapistId);

        // ----- Tabs logic -----
        if ($tab = $request->query('tab')) {

            if ($tab === 'newest') {
                // شات فيه message من client مش مقروءة من الدكتور
                $q->whereHas('messages', function ($m) use ($doctorUserId) {
                    $m->where('sender_role', 'client')
                      ->whereDoesntHave('reads', function ($r) use ($doctorUserId) {
                          $r->where('user_id', $doctorUserId);
                      });
                });
            }

            if ($tab === 'oldest') {
                // شات مفيهوش message من client غير مقروءة من الدكتور
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

        // search by client name / email
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
