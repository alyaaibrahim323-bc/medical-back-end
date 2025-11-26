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

    // 🟢 مؤقتاً: اسمح لأي يوزر Authenticated يدخل أي chat (بس علشان نتأكد إن مفيش 403)
    // لما تتأكدي إن كله شغال، نرجّع الشروط اللي تحتها
    return true;

    // --- بعد كده رجّعي الشروط دي بدل return true ---

    // // client
    // if ($user->hasRole('client')) {
    //     return Chat::where('id', $chatId)
    //         ->where('user_id', $user->id)
    //         ->exists();
    // }

    // // doctor
    // if ($user->hasRole('doctor')) {
    //     $therapistId = optional($user->therapist)->id;
    //     if (! $therapistId) {
    //         return false;
    //     }

    //     return Chat::where('id', $chatId)
    //         ->where('therapist_id', $therapistId)
    //         ->exists();
    // }

    // // admin
    // if ($user->hasRole('admin')) {
    //     return true;
    // }

    // return false;
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

    // 🟢 مؤقتاً: أي يوزر Authenticated يدخل أي notifications
    // بعد التأكد من ال IDs هنرجع الشرط القديم
    return true;

    // --- بعد كده رجّعي الشرط ده بدل return true ---
    // return (int) $user->id === (int) $userId;
});
