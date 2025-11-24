<?php

namespace App\Policies;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChatPolicy
{
    use HandlesAuthorization;

    // مين يقدر يشوف الشات
    public function view(User $user, Chat $chat): bool
    {
        return $this->participate($user, $chat) || $user->hasRole('admin');
    }

    // مين طرف في الشات (client / doctor assigned)
    public function participate(User $user, Chat $chat): bool
    {
        $isClient = $user->id === $chat->user_id;

        $therapistUserId = optional($chat->therapist)->user_id;
        $isTherapist = $therapistUserId && $user->id === $therapistUserId;

        return $isClient || $isTherapist;
    }

    // مين له حق يبعث رسالة
    public function message(User $user, Chat $chat): bool
    {
        // لو الشات مقفول خلاص
        if ($chat->status === 'closed') {
            return false;
        }

        // Admin يكتب في أي وقت في أي شات
        if ($user->hasRole('admin')) {
            return true;
        }

        // لأي حد تاني: لازم يكون طرف في الشات
        return $this->participate($user, $chat);
    }

    // تعيين الشات لدكتور أو قفله → Admin بس
    public function assign(User $user, Chat $chat): bool
    {
        return $user->hasRole('admin');
    }
}
