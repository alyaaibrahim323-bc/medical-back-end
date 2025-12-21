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
    $base = Chat::query()->with(['session','user','therapist']);

    if ($type = $request->get('type')) {
        $base->where('type', $type);
    }

    if ($search = $request->get('search')) {
        $base->whereHas('user', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    if ($from = $request->get('from')) {
        $base->whereDate('last_message_at', '>=', $from);
    }
    if ($to = $request->get('to')) {
        $base->whereDate('last_message_at', '<=', $to);
    }

    // ---------------- COUNTS ----------------
    $counts = [
        'all' => (clone $base)->count(),
        'pending' => (clone $base)->where(function($q){
            $q->where('status','pending')->orWhereNull('status');
        })->count(),
        'replied' => (clone $base)->where('status','replied')->count(),
        'closed'  => (clone $base)->where('status','closed')->count(),
    ];

    // ---------------- LIST FILTER ----------------
    $q = clone $base;

    // 👇 يقبل tab أو status
    $status = $request->get('tab') ?? $request->get('status');

    if ($status === 'pending') {
        $q->where(function($qq){
            $qq->where('status','pending')->orWhereNull('status');
        });
    } elseif (in_array($status, ['replied','closed'], true)) {
        $q->where('status', $status);
    }

    $chats = $q->orderByDesc('last_message_at')->paginate(20);

    return AdminChatResource::collection($chats)->additional([
        'counts' => $counts,
    ]);
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
