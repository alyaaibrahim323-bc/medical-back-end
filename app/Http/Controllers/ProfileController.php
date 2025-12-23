<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
  
    public function show(Request $r)
    {
        $user = $r->user();

        return response()->json([
            'data' => $this->transformUser($user),
        ]);
    }


    public function updateProfileInfo(Request $r)
    {
        $user = $r->user();

        $data = $r->validate([
            'name'  => ['sometimes','string','max:100'],
            'email' => ['sometimes','email','max:255'],
            'phone' => ['sometimes','string','max:30'],
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $this->transformUser($user->fresh()),
        ]);
    }

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

        if (Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'New password must be different from current password.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

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
        ];
    }

    
    protected function avatarUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }
}
