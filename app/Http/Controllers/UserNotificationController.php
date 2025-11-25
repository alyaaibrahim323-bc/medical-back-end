<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\NotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    /**
     * GET /me/notifications
     * يرجّع ليست النوتيفيكيشن بتاعة اليوزر (مع الـ pagination)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $deliveries = NotificationDelivery::with('notification')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        // نخلي resource تشتغل على notification نفسه + pivot read_at
        $notifications = $deliveries->getCollection()->map(function ($delivery) {
            // نحط الـ pivot جوه الموديل عشان NotificationResource تقراه
            $notification = $delivery->notification;
            $notification->setRelation('pivot', $delivery);

            return $notification;
        });

        $deliveries->setCollection($notifications);

        return NotificationResource::collection($deliveries);
    }

    /**
     * PATCH /me/notifications/{notification}/read
     * بيعلّم نوتيفيكيشن معينة إنها مقروءة
     */
    public function markAsRead(Request $request, int $notification): JsonResponse
    {
        $user = $request->user();

        $delivery = NotificationDelivery::where('notification_id', $notification)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (is_null($delivery->read_at)) {
            $delivery->update(['read_at' => now()]);
        }

        return response()->json([
            'ok'            => true,
            'notification_id' => $notification,
            'read_at'       => $delivery->read_at,
        ]);
    }

    /**
     * PATCH /me/notifications/read-all
     * بيعلّم كل النوتيفيكيشن كـ read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        NotificationDelivery::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'ok'   => true,
            'message' => 'All notifications marked as read.',
        ]);
    }
}
