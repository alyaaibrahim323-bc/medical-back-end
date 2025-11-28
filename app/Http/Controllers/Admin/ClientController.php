<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TherapySession;
use App\Models\UserPackage;
use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * قائمة الكلاينتس (All / Active / Blocked + Search)
     * GET /admin/clients?status=active|blocked&search=...
     */
public function index(Request $r)
{
    // base query (من غير status)
    $base = User::query()
        ->where('role', 'user')
        ->when($r->filled('search'), function ($x) use ($r) {
            $s = $r->search;
            $x->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('id', $s);
            });
        });

    // 👈 الأرقام لكل تاب
    $counts = [
        'all'     => (clone $base)->count(),
        'active'  => (clone $base)->where('status', 'active')->count(),
        'blocked' => (clone $base)->where('status', 'blocked')->count(),
    ];

    // الفلتر اللى جاى من الواجهة
    $q = clone $base;
    if ($r->filled('status') && in_array($r->status, ['active','blocked'], true)) {
        $q->where('status', $r->status);
    }

    $clients = $q->orderByDesc('id')->paginate(20);

    return response()->json([
        'data'   => $clients,
        'counts' => $counts,
    ]);
}

    /**
     * تفاصيل Client basic info + شوية إحصائيات سريعة
     * باقي التفاصيل (sessions/subscriptions) من:
     *   /admin/therapy-sessions?user_id=...
     *   /admin/subscriptions?user_id=...
     */
   public function show($id)
{
    $user = User::where('role', 'user')->findOrFail($id);

    $totalSessions   = TherapySession::where('user_id', $user->id)->count();
    $upcomingSessions= TherapySession::where('user_id', $user->id)
        ->where('scheduled_at','>=', now())
        ->count();
    $completedSessions = TherapySession::where('user_id', $user->id)
        ->where('status', TherapySession::STATUS_COMPLETED)
        ->count();

    $activeSubscriptions = UserPackage::where('user_id', $user->id)
        ->where('status', 'active')
        ->count();

    $totalSpentCents = Payment::where('user_id', $user->id)
        ->where('status', Payment::STATUS_PAID)
        ->sum('amount_cents');

    return response()->json([
        'data' => [
            'client' => $user,
            'stats'  => [
                'sessions_total'      => $totalSessions,
                'sessions_upcoming'   => $upcomingSessions,
                'sessions_completed'  => $completedSessions,
                'subscriptions_active'=> $activeSubscriptions,
                'total_spent_cents'   => $totalSpentCents,
            ],
        ],
    ]);
}


    /**
     * Block / Active
     * PATCH /admin/clients/{id}/status  { "status": "active" | "blocked" }
     */
    public function updateStatus(Request $r, $id)
    {
        $data = $r->validate([
            'status' => ['required','in:active,blocked'],
        ]);

        $user = User::where('role','user')->findOrFail($id);
        $user->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'Client status updated',
            'data'    => $user,
        ]);
    }

    /**
     * زرار Send Notification من شاشة Client Details
     * POST /admin/clients/{id}/notify
     * body:
     *  {
     *    "type": "system_update",
     *    "data": { ... optional ... }
     *  }
     * أو ممكن تبعتي title/body جاهزين لو حابة.
     */
    public function sendNotification(Request $r, $id, NotificationService $service)
    {
        $user = User::where('role','user')->findOrFail($id);

        $data = $r->validate([
            'type' => ['required','string'],
            'data' => ['nullable','array'],
        ]);

        $notification = $service->sendToUser(
            $user->id,
            $data['type'],
            $data['data'] ?? []
        );

        return response()->json([
            'message' => 'Notification sent to client',
            'data'    => $notification,
        ]);
    }
}
