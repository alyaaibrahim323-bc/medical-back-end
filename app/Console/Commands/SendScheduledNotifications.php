<?php

// app/Console/Commands/SendScheduledNotifications.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Events\NotificationSent;
use App\Http\Resources\NotificationResource;
use App\Models\User;

class SendScheduledNotifications extends Command
{
    protected $signature = 'notifications:send-scheduled';
    protected $description = 'Send scheduled admin notifications whose time has come';

    public function handle(): int
    {
        $now = now();

        Notification::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->chunkById(50, function ($notifications) {
                foreach ($notifications as $notification) {
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
                        NotificationDelivery::firstOrCreate([
                            'notification_id' => $notification->id,
                            'user_id'         => $user->id,
                        ], [
                            'delivered_at'    => now(),
                        ]);

                        broadcast(new NotificationSent($user->id, $payload))->toOthers();
                    }

                    $notification->update([
                        'status'  => 'sent',
                        'sent_at' => now(),
                    ]);
                }
            });

        $this->info('Scheduled notifications processed.');

        return static::SUCCESS;
    }
}

