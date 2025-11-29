<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * رجوع بيانات اليوزر الحالي (profile screen).
     */
    public function show(Request $r)
    {
        $user = $r->user();

        return response()->json([
            'data' => $this->transformUser($user),
        ]);
    }

    /**
     * تحديث الاسم / الإيميل / التليفون.
     */
    public function updateProfileInfo(Request $r)
    {
        $user = $r->user();

        $data = $r->validate([
            'name'  => ['sometimes','string','max:100'],
            'email' => ['sometimes','email','max:255'],
            'phone' => ['sometimes','string','max:30'],
            // لو حابة تخليهم يغيروا اللغة من البروفايل:
            // 'preferred_locale' => ['sometimes','in:en,ar'],
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $this->transformUser($user->fresh()),
        ]);
    }

    /**
     * تحديث الصورة الشخصية.
     */
    public function updateAvatar(Request $r)
    {
        $user = $r->user();

        $data = $r->validate([
            'avatar' => ['required','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ]);

        $avatarPath = $r->file('avatar')->store('avatars/users', 'public');

        $user->update([
            'avatar' => $avatarPath,
        ]);

        return response()->json([
            'message'    => 'Avatar updated successfully.',
            'avatar'     => $avatarPath,
            'avatar_url' => $this->avatarUrl($avatarPath),
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
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ]);

        // ممنوع الباسورد الجديد = القديم
        if (Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'New password must be different from current password.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        // لو بتستخدمي Sanctum: نلغى التوكينات القديمة (ماعدا الحالي)
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

    /**
     * Helper: توحيد شكل بيانات اليوزر الراجعة فى الـ API.
     */
    protected function transformUser($user): array
    {
        $avatarPath = $user->avatar;

        return [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'phone'            => $user->phone,
            'preferred_locale' => $user->preferred_locale,
            'status'           => $user->status,
            'avatar'           => $avatarPath,
            'avatar_url'       => $this->avatarUrl($avatarPath),
            'email_verified_at'=> $user->email_verified_at,
            // لو محتاجة تضيفى في المستقبل حقول زيادة (role, etc...) ضيفيها هنا
        ];
    }

    /**
     * Helper: بناء لينك الصورة من مسار التخزين.
     */
    protected function avatarUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }
}
