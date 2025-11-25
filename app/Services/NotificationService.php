<?php

namespace App\Services;

use App\Events\NotificationSent;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class NotificationService
{
    /**
     * Broadcast to single user
     */
    public function sendToUser(int $userId, string $type, array $data = []): Notification
    {
        $notification = Notification::create([
            'type'    => $type,
            'data'    => $data,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        NotificationDelivery::create([
            'notification_id' => $notification->id,
            'user_id'         => $userId,
            'delivered_at'    => now(),
        ]);

        // نحول الـ model لـ array بالـ Resource (عشان الـ Event يستقبل array)
        $payload = (new NotificationResource($notification))->resolve();

        broadcast(new NotificationSent($userId, $payload))->toOthers();

        return $notification;
    }

    /**
     * Broadcast to many users (all / segment)
     *
     * @param EloquentCollection<int,User> $users
     */
    public function sendToMany(EloquentCollection $users, string $type, array $data = []): Notification
    {
        $notification = Notification::create([
            'type'    => $type,
            'data'    => $data,
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        $payload = (new NotificationResource($notification))->resolve();

        foreach ($users as $user) {
            /** @var User $user */ // عشان Intelephense يبطل يقول TValue

            NotificationDelivery::create([
                'notification_id' => $notification->id,
                'user_id'         => $user->id,
                'delivered_at'    => now(),
            ]);

            broadcast(new NotificationSent($user->id, $payload))->toOthers();
        }

        return $notification;
    }

    /**
     * مثال: إشعار جلسة قادمة
     */
    public function sendSessionUpcoming(\App\Models\TherapySession $session): void
    {
        $this->sendToUser(
            $session->user_id,
            'session_upcoming',
            [
                'doctor' => $session->therapist->user->name,
                'time'   => $session->scheduled_at->format('g:i A'),
            ]
        );
    }

    /**
     * مثال: إشعار سيستم أبديت للكل
     */
    public function sendSystemUpdate(string $message): void
    {
        $users = User::all(); // بيرجع EloquentCollection<User>

        $this->sendToMany($users, 'system_update', [
            'message' => $message,
        ]);
    }
}
