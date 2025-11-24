<?php

use App\Models\Chat;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.{chatId}', function ($user, int $chatId) {
    // client
    if ($user->hasRole('client')) {
        return Chat::where('id', $chatId)
            ->where('user_id', $user->id)
            ->exists();
    }

    // doctor
    if ($user->hasRole('doctor')) {
        return Chat::where('id', $chatId)
            ->where('therapist_id', optional($user->therapist)->id)
            ->exists();
    }

    // admin
    if ($user->hasRole('admin')) {
        return true;
    }

    return false;
});

