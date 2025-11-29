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
    $user = $request->user();
    $therapistId = optional($user->therapist)->id;

    if (!$therapistId) {
        return response()->json([
            'message' => 'No therapist profile attached to this user.',
        ], 403);
    }

    // ============================================
    // Counts: unread / read / all
    // ============================================
    $base = Chat::where('therapist_id', $therapistId)
        ->with(['messages.reads'])
        ->get();

    // unread لو فيه message واحدة على الأقل مش متقريّة من الثيرابست
    $unreadCount = $base->filter(function ($chat) use ($therapistId) {
        return $chat->messages->contains(function ($msg) use ($therapistId) {
            return !$msg->reads->where('user_id', $therapistId)->count();
        });
    })->count();

    // read = chats that have zero unread messages
    $readCount = $base->count() - $unreadCount;

    $counts = [
        'all'    => $base->count(),
        'newest' => $unreadCount,   // unread
        'oldest' => $readCount,     // read
    ];

    // ============================================
    // Query الرئيسية
    // ============================================
    $q = Chat::with(['session', 'user'])
        ->where('therapist_id', $therapistId);

    // tab filter
    if ($tab = $request->query('tab')) {

        if ($tab === 'newest') {
            // unread only
            $q->whereHas('messages', function ($m) use ($therapistId) {
                $m->whereDoesntHave('reads', function ($r) use ($therapistId) {
                    $r->where('user_id', $therapistId);
                });
            });
        }

        if ($tab === 'oldest') {
            // read only (all messages read)
            $q->whereDoesntHave('messages', function ($m) use ($therapistId) {
                $m->whereDoesntHave('reads', function ($r) use ($therapistId) {
                    $r->where('user_id', $therapistId);
                });
            });
        }
    }

    // search
    if ($search = $request->query('search')) {
        $q->whereHas('user', function ($u) use ($search) {
            $u->where('name', 'like', "%{$search}%")
              ->orWhere('email','like', "%{$search}%");
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
