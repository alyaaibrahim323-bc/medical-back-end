<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignChatRequest;
use App\Http\Resources\AdminChatResource;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use Illuminate\Http\Request;

class AdminChatController extends Controller
{
    public function index(Request $request)
    {
        $query = Chat::with(['session','user','therapist']);

        // فلترة بسيطة حسب النوع (support) أو لو سيبنا session للمستقبل
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($tab = $request->get('tab')) {
            if ($tab === 'pending') {
                $query->where('status','pending');
            } elseif ($tab === 'replied') {
                $query->where('status','replied');
            } elseif ($tab === 'closed') {
                $query->where('status','closed');
            }
        }

        if ($search = $request->get('search')) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($from = $request->get('from')) {
            $query->whereDate('last_message_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('last_message_at', '<=', $to);
        }

        $chats = $query->latest('last_message_at')->paginate(20);

        return AdminChatResource::collection($chats);
    }

    public function assign(AssignChatRequest $request, Chat $chat)
    {
        $this->authorize('assign', $chat);

        $chat->update([
            'therapist_id' => $request->therapist_id,   // لو هيترد من دكتور
            'assigned_by'  => $request->user()->id,
            'assigned_at'  => now(),
        ]);

        return new ChatResource($chat->fresh(['session','user','therapist']));
    }

    public function close(Request $request, Chat $chat)
    {
        $this->authorize('assign', $chat);

        $chat->update(['status' => 'closed']);

        return response()->json(['ok' => true]);
    }
}
