<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
USE Illuminate\Support\Facades\Log;

class UserProfileController extends Controller
{
    /**
     * رجوع بيانات اليوزر الحالي (profile screen).
     */
    public function show(Request $r)
    {
        $user = $r->user(); // user مسجّل دخول

        return response()->json([
            'data' => $user,
        ]);
    }

    /**
     * تحديث الاسم / الإيميل / التليفون / الصورة.
     */
 public function update(Request $r)
{
    $user = $r->user();

    $data = $r->validate([
        'name'   => ['sometimes','string','max:100'],
        'email'  => ['sometimes','email','max:255'],
        'phone'  => ['sometimes','string','max:30'],
        'avatar' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
    ]);

    if ($r->hasFile('avatar')) {
        $avatarPath     = $r->file('avatar')->store('avatars/users', 'public');
        $data['avatar'] = $avatarPath;
    }

    $user->update($data);

    return response()->json([
        'data' => $user->fresh(),
    ]);
}



    /**
     * تغيير الباسورد.
     */
    public function updatePassword(Request $r)
    {
        $user = $r->user();

        $data = $r->validate([
            'current_password' => ['required','current_password:sanctum'],
            'password' => [
                'required','confirmed',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ]);

        // الباسورد الجديد مايبقاش نفس القديم
        if (Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'New password must be different from current password.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        // نلغي كل التوكنز القديمة (ما عدا الحالي)
        if (method_exists($user, 'tokens')) {
            $currentTokenId = optional($r->user()->currentAccessToken())->id;

            $user->tokens()
                ->when($currentTokenId, fn($q) => $q->where('id', '!=', $currentTokenId))
                ->delete();
        }

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }
}
