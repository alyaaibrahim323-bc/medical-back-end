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

        $chats = Chat::with(['session', 'user'])
            ->where('therapist_id', $therapistId)
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
}
