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
    $doctorUserId = $user->id; // 👈 ده اللى بيتخزن فى chat_reads.user_id

    if (!$therapistId) {
        return response()->json([
            'message' => 'No therapist profile attached to this user.',
        ], 403);
    }

    // ============================================
    // Counts: all / newest(unread) / oldest(read)
    // ============================================
    $base = Chat::where('therapist_id', $therapistId)
        ->with(['messages.reads'])
        ->get();

    // 👇 chat يعتبر UNREAD لو فيه message واحدة على الأقل مش متقريّة من الـ doctor user
    $unreadCount = $base->filter(function ($chat) use ($doctorUserId) {
        return $chat->messages->contains(function ($msg) use ($doctorUserId) {
            // لو مفيش أى record فى reads بنفس user_id → الرسالة دى مش مقروءة من الدكتور
            return !$msg->reads->where('user_id', $doctorUserId)->count();
        });
    })->count();

    // read = كل الرسائل في الشات متقريّة (مفيش أى unread)
    $readCount = $base->count() - $unreadCount;

    $counts = [
        'all'    => $base->count(),
        'newest' => $unreadCount,   // unread
        'oldest' => $readCount,     // read
    ];

    // ============================================
    // Query الرئيسية لقائمة الشات
    // ============================================
    $q = Chat::with([
            'session',
            'user',             // client
            'therapist.user',   // doctor info
            'lastMessage',      // لو ضفنا الريليشن دى فى الموديل
        ])
        ->where('therapist_id', $therapistId);

    // tab filter
    if ($tab = $request->query('tab')) {

        if ($tab === 'newest') {
            // 👈 Chats فيها على الأقل message واحدة مش متقريّة من الدكتور
            $q->whereHas('messages', function ($m) use ($doctorUserId) {
                $m->whereDoesntHave('reads', function ($r) use ($doctorUserId) {
                    $r->where('user_id', $doctorUserId);
                });
            });
        }

        if ($tab === 'oldest') {
            // 👈 Chats مفيهاش ولا message "unread" للدكتور
            $q->whereDoesntHave('messages', function ($m) use ($doctorUserId) {
                $m->whereDoesntHave('reads', function ($r) use ($doctorUserId) {
                    $r->where('user_id', $doctorUserId);
                });
            });
        }
    }

    // search by client name / email
    if ($search = $request->query('search')) {
        $q->whereHas('user', function ($u) use ($search) {
            $u->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    $chats = $q->latest('last_message_at')->paginate(20);

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
