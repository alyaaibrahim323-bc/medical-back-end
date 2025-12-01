<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Events\NotificationSent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminNotificationController extends Controller
{
    /**
     * GET /admin/notifications
     * يدعم:
     *  - status=sent|scheduled|draft
     *  - from=2025-01-01
     *  - to=2025-01-31
     *  - search=string
     */
   public function index(Request $r)
{
    // ----- BASE QUERY (بدون status) -----
    $base = Notification::query()
        ->withCount('deliveries')
        ->orderByDesc('created_at');

    // نفس الفلاتر (search / from / to) هتتطبّق على الـ counts والـ list
    if ($search = $r->query('search')) {
        $base->where(function ($qq) use ($search) {
            $qq->where('title_en', 'like', "%{$search}%")
               ->orWhere('title_ar', 'like', "%{$search}%")
               ->orWhere('body_en', 'like', "%{$search}%")
               ->orWhere('body_ar', 'like', "%{$search}%");
        });
    }

    if ($from = $r->query('from')) {
        $base->whereDate('created_at', '>=', $from);
    }

    if ($to = $r->query('to')) {
        $base->whereDate('created_at', '<=', $to);
    }

    // ----- COUNTS لكل التابات -----
    $counts = [
        'all'       => (clone $base)->count(),
        'sent'      => (clone $base)->where('status', 'sent')->count(),
        'scheduled' => (clone $base)->where('status', 'scheduled')->count(),
        'draft'     => (clone $base)->where('status', 'draft')->count(),
    ];

    // ----- LIST مع فلتر status لو موجود -----
    $q = clone $base;

    if ($status = $r->query('status')) {
        $q->where('status', $status);
    }

    $notifications = $q->paginate(20);

    return NotificationResource::collection($notifications)
    ->additional([
        'counts' => $counts
    ]);
}


    /**
     * GET /admin/notifications/{id}
     * تفاصيل إشعار واحد
     */
    public function show($id)
    {
        $notification = Notification::with(['deliveries','creator'])
            ->withCount('deliveries')
            ->findOrFail($id);

        return new NotificationResource($notification);
    }

    /**
     * POST /admin/notifications
     * إنشاء + Send Now / Schedule
     *
     * Body مثال:
     * {
     *   "title_en": "System Update",
     *   "title_ar": "تحديث في النظام",
     *   "body_en": "We updated the app...",
     *   "body_ar": "قمنا بتحديث التطبيق...",
     *   "send_to": "all" | "specific",
     *   "users": [1,2,3],                // required if send_to=specific
     *   "schedule_type": "now" | "later",
     *   "scheduled_at": "2025-11-25 15:00:00" // required if later
     * }
     */
    public function store(Request $r)
    {
        $data = $r->validate([
            'title_en'      => 'required|string',
            'title_ar'      => 'required|string',
            'body_en'       => 'required|string',
            'body_ar'       => 'required|string',
            'send_to'       => 'required|in:all,specific',
            'users'         => 'required_if:send_to,specific|array',
            'schedule_type' => 'required|in:now,later',
            'scheduled_at' => 'required_if:schedule_type,later|date',
        ]);

        $scheduleType = $data['schedule_type'];
        $sendTo       = $data['send_to'];

        $scheduledFor = $scheduleType === 'later'
            ? Carbon::parse($data['scheduled_at'])
            : null;

        $status = $scheduleType === 'now' ? 'sent' : 'scheduled';

        // نخزن audience في data عشان الـ job يستخدمها للـ scheduled
        $extraData = [
            'send_to'  => $sendTo,
            'user_ids' => $sendTo === 'specific' ? $data['users'] : [],
        ];

        $notification = Notification::create([
            'type'         => 'admin_broadcast',
            'title_en'     => $data['title_en'],
            'title_ar'     => $data['title_ar'],
            'body_en'      => $data['body_en'],
            'body_ar'      => $data['body_ar'],
            'data'         => $extraData,
            'status'       => $status,
            'created_by'   => $r->user()->id,
            'sent_at'      => $status === 'sent' ? now() : null,
            'scheduled_at'=> $scheduledFor,
        ]);

        // لو Send Now → نبعته فورًا
        if ($status === 'sent') {
            $this->deliverNotificationNow($notification);
        }

        return new NotificationResource($notification);
    }

    /**
     * PATCH /admin/notifications/{id}
     * تعديل إشعار (عادةً scheduled فقط)
     */
    public function update(Request $r, $id)
    {
        $notification = Notification::findOrFail($id);

        // لو بعت قبل كده خلاص مش هنعدّله
        if ($notification->status === 'sent') {
            return response()->json([
                'message' => 'Cannot edit a sent notification.',
            ], 422);
        }

        $data = $r->validate([
            'title_en'      => 'sometimes|string',
            'title_ar'      => 'sometimes|string',
            'body_en'       => 'sometimes|string',
            'body_ar'       => 'sometimes|string',
            'send_to'       => 'sometimes|in:all,specific',
            'users'         => 'required_if:send_to,specific|array',
            'schedule_type' => 'sometimes|in:now,later',
            'scheduled_at' => 'required_if:schedule_type,later|date',
        ]);

        // نحدث الـ data (audience) لو اتغيّرت
        $currentData = $notification->data ?? [];

        if (isset($data['send_to'])) {
            $currentData['send_to']  = $data['send_to'];
            $currentData['user_ids'] = $data['send_to'] === 'specific'
                ? ($data['users'] ?? [])
                : [];
        }

        $notification->fill([
            'title_en' => $data['title_en'] ?? $notification->title_en,
            'title_ar' => $data['title_ar'] ?? $notification->title_ar,
            'body_en'  => $data['body_en'] ?? $notification->body_en,
            'body_ar'  => $data['body_ar'] ?? $notification->body_ar,
            'data'     => $currentData,
        ]);

        // لو اتغيّر الـ schedule_type
        if (isset($data['schedule_type'])) {
            if ($data['schedule_type'] === 'now') {
                $notification->status       = 'sent';
                $notification->scheduled_at = null;
                $notification->sent_at      = now();

                $notification->save();

                $this->deliverNotificationNow($notification);

                return new NotificationResource($notification);
            }

            if ($data['schedule_type'] === 'later') {
                $notification->status        = 'scheduled';
                $notification->scheduled_at = Carbon::parse($data['scheduled_at']);
            }
        }

        $notification->save();

        return new NotificationResource($notification);
    }

    /**
     * DELETE /admin/notifications/{id}
     * حذف إشعار (حتى لو كان sent)
     */
    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);

        // ممكن تختاري تمنعي حذف sent
        // if ($notification->status === 'sent') {
        //     return response()->json(['message' => 'Cannot delete sent notification'], 422);
        // }

        $notification->deliveries()->delete();
        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }

    /**
     * إرسال إشعار فورًا (لـ Send Now أو لما يتحوّل من scheduled إلى now)
     */
    protected function deliverNotificationNow(Notification $notification): void
    {
        $data    = $notification->data ?? [];
        $sendTo  = $data['send_to']  ?? 'all';
        $userIds = $data['user_ids'] ?? [];

        if ($sendTo === 'specific' && !empty($userIds)) {
            $users = User::whereIn('id', $userIds)->get();
        } else {
            $users = User::all();
        }

        $payload = (new NotificationResource($notification))->resolve();

        foreach ($users as $user) {
            NotificationDelivery::create([
                'notification_id' => $notification->id,
                'user_id'         => $user->id,
                'delivered_at'    => now(),
            ]);

            broadcast(new NotificationSent($user->id, $payload))->toOthers();
        }
    }
}
