<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChatResource;
use App\Models\Chat;
use Illuminate\Http\Request;

class UserChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $chats = Chat::with(['session', 'therapist'])
            ->where('user_id', $user->id)
            ->latest('last_message_at')
            ->paginate(20);

        return ChatResource::collection($chats);
    }

    public function show(Request $request, Chat $chat)
    {
        $this->authorize('view', $chat);

        $chat->load(['session', 'user', 'therapist']);

        return new ChatResource($chat);
    }

    // 📌 ده المهم: فتح / إرجاع شات support
    public function openSupportChat(Request $request)
{
    $user = $request->user();

    // 👈 هنا بدوّر بس على شات support "نضيف"
    $chat = Chat::where('user_id', $user->id)
        ->where('type', 'support')
        ->whereNull('therapy_session_id')   // مالوش علاقة بسيشن
        ->first();

    // لو مفيش → أعمل واحد جديد
    if (! $chat) {
        $chat = Chat::create([
            'user_id'            => $user->id,
            'type'               => 'support',
            'therapy_session_id' => null,
            'therapist_id'       => null,
            'status'             => 'pending',
        ]);
    }

    // ممكن لو حابة تشيلي session من الـ resource في حالة support
    return new ChatResource(
        $chat->load(['session', 'therapist'])
    );
}

}
