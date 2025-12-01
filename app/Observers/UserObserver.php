<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Therapist;

class UserObserver
{
    public function created(User $user): void
    {
        // لو اتخلق أصلاً دكتور نعمله record
        if ($user->role === 'doctor') {
            $this->ensureTherapistExists($user);
        }
    }

    public function updated(User $user): void
    {
        // لو كان مش دكتور وبقى دكتور
        if ($user->wasChanged('role') && $user->role === 'doctor') {
            $this->ensureTherapistExists($user);
        }
    }

    protected function ensureTherapistExists(User $user): void
    {
        Therapist::firstOrCreate(
            ['user_id' => $user->id],
            [
                'specialty'   => ['en' => ''],
                'bio'         => ['en' => ''],
                'price_cents' => 0,
                'currency'    => 'EGP',
                'is_active'   => true, // لحد ما الأدمن يفعّله
            ]
        );
    }
}
