<?php

use App\Models\Chat;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

// ============ CHAT CHANNEL ============
Broadcast::channel('chat.{chatId}', function ($user, int $chatId) {
    Log::info('CHAT BROADCAST AUTH CHECK', [
        'auth_user_id' => optional($user)->id,
        'chat_id'      => $chatId,
        'roles'        => $user ? $user->getRoleNames() : [],
    ]);

    if (! $user) {
        return false;
    }

    // client
    if ($user->hasRole('client')) {
        return Chat::where('id', $chatId)
            ->where('user_id', $user->id)
            ->exists();
    }

    // doctor
    if ($user->hasRole('doctor')) {
        $therapistId = optional($user->therapist)->id;
        if (! $therapistId) {
            return false;
        }

        return Chat::where('id', $chatId)
            ->where('therapist_id', $therapistId)
            ->exists();
    }

    // admin
    if ($user->hasRole('admin')) {
        return true;
    }

    return false;
});

// ============ NOTIFICATIONS CHANNEL ============
Broadcast::channel('notifications.user.{userId}', function ($user, int $userId) {
    Log::info('NOTIF BROADCAST AUTH CHECK', [
        'auth_user_id'    => optional($user)->id,
        'channel_user_id' => $userId,
    ]);

    if (! $user) {
        return false;
    }

    // كل يوزر يشوف notifications بتاعته بس
    return (int) $user->id === (int) $userId;
});
