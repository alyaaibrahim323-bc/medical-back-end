<?php

namespace App\Policies;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChatPolicy
{
    use HandlesAuthorization;

    private function isAdmin(User $user): bool
{
    return strtolower((string)$user->role) === 'admin'
        || $user->hasRole('admin');
}

   public function view(User $user, Chat $chat): bool
{
    return $this->participate($user, $chat) || $this->isAdmin($user);
}

    public function participate(User $user, Chat $chat): bool
    {
        $isClient = $user->id === $chat->user_id;

        $therapistUserId = optional($chat->therapist)->user_id;
        $isTherapist = $therapistUserId && $user->id === $therapistUserId;

        return $isClient || $isTherapist;
    }

   public function message(User $user, Chat $chat): bool
{
    if ($chat->status === 'closed') return false;
    return $this->isAdmin($user) || $this->participate($user, $chat);
}

 public function assign(User $user, Chat $chat): bool
{
    return $this->isAdmin($user);
}

}
