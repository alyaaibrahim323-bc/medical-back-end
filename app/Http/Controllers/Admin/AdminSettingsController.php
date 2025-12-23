<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class AdminSettingsController extends Controller
{
    
    public function show(Request $r)
    {
        $admin = $r->user();

        return response()->json([
            'data' => [
                'id'          => $admin->id,
                'name'        => $admin->name,
                'email'       => $admin->email,
                'phone'       => $admin->phone,
                'avatar'      => $admin->avatar,
                'avatar_url'  => $admin->avatar
                    ? Storage::disk('public')->url($admin->avatar)
                    : null,
                'last_login'  => $admin->last_login_at ?? null,
                'role'        => $admin->role,
                'created_at'  => $admin->created_at,
            ],
        ]);
    }

    
    public function updateProfile(Request $r)
    {
        $admin = $r->user();

        $data = $r->validate([
            'name'  => ['sometimes','string','max:120'],
            'email' => [
                'sometimes','email','max:255',
                Rule::unique('users','email')->ignore($admin->id),
            ],
            'phone' => ['sometimes','string','max:30'],
        ]);

        $admin->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => $admin->fresh(),
        ]);
    }

    public function updateAvatar(Request $r)
    {
        $admin = $r->user();

        $data = $r->validate([
            'avatar' => ['required','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ]);

        if ($admin->avatar && Storage::disk('public')->exists($admin->avatar)) {
            Storage::disk('public')->delete($admin->avatar);
        }

        $path = $r->file('avatar')->store('avatars/admins', 'public');

        $admin->update(['avatar' => $path]);

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'data'    => [
                'avatar'     => $admin->avatar,
                'avatar_url' => Storage::disk('public')->url($admin->avatar),
            ],
        ]);
    }

    public function updatePassword(Request $r)
    {
        $admin = $r->user();

        $data = $r->validate([
            'current_password' => ['required','current_password:sanctum'],
            'password'         => [
                'required','confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ]);

        if (Hash::check($data['password'], $admin->password)) {
            return response()->json([
                'message' => 'New password must be different from current password.',
            ], 422);
        }

        $admin->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        if (method_exists($admin, 'tokens')) {
            $currentTokenId = optional($r->user()->currentAccessToken())->id;

            $admin->tokens()
                ->when($currentTokenId, fn($q) => $q->where('id','!=',$currentTokenId))
                ->delete();
        }

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }
}
