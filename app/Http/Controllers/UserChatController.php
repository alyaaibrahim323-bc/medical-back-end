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

    public function openSupportChat(Request $request)
{
    $user = $request->user();

    $chat = Chat::where('user_id', $user->id)
        ->where('type', 'support')
        ->whereNull('therapy_session_id')   
        ->first();

    if (! $chat) {
        $chat = Chat::create([
            'user_id'            => $user->id,
            'type'               => 'support',
            'therapy_session_id' => null,
            'therapist_id'       => null,
            'status'             => 'pending',
        ]);
    }

    return new ChatResource(
        $chat->load(['session', 'therapist'])
    );
}

}
