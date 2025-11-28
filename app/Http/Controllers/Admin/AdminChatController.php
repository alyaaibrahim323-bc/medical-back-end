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
    // ===== 1) Base Query من غير status (عشان نطلع counts مظبوطة) =====
    $base = Chat::with(['session','user','therapist']);

    // فلترة حسب النوع (support / session) لو عايزين
    if ($type = $request->get('type')) {
        $base->where('type', $type);
    }

    // search by user name / email
    if ($search = $request->get('search')) {
        $base->whereHas('user', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    // فلتر التاريخ
    if ($from = $request->get('from')) {
        $base->whereDate('last_message_at', '>=', $from);
    }
    if ($to = $request->get('to')) {
        $base->whereDate('last_message_at', '<=', $to);
    }

    // ===== 2) الـ counts لكل تاب =====
    $counts = [
        'all'     => (clone $base)->count(),
        'pending' => (clone $base)->where('status', 'pending')->count(),
        'replied' => (clone $base)->where('status', 'replied')->count(),
        'closed'  => (clone $base)->where('status', 'closed')->count(),
    ];

    // ===== 3) الـ List الفعلية حسب التاب المختار =====
    $q = clone $base;

    if ($tab = $request->get('tab')) {
        if (in_array($tab, ['pending','replied','closed'], true)) {
            $q->where('status', $tab);
        }
    }

    $chats = $q->orderByDesc('last_message_at')->paginate(20);

    // نرجّع الـ data + counts
    return response()->json([
        'data'   => AdminChatResource::collection($chats),
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
