<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
  
    
    public function show(Request $r)
    {
        $t = $r->user()->therapist()->with('user')->firstOrFail();

        return response()->json([
            'data' => $t,
        ]);
    }


    public function updateProfileData(Request $r)
    {
        $t = $r->user()->therapist()->firstOrFail();

        $data = $r->validate([
            'bio'              => ['sometimes'],
            'specialty'        => ['sometimes'],
            'years_experience' => ['sometimes','integer','min:0','max:80'],
            'languages'        => ['sometimes','array'],
            'certificates'     => ['sometimes','array'],
            'price_cents'      => ['sometimes','integer','min:0'],
            'currency'         => ['sometimes','string','size:3'],
            'is_active'        => ['sometimes','boolean'],

            'name'   => ['sometimes','string','max:100'],
            'email'  => ['sometimes','email','max:255'],
            'phone'  => ['sometimes','string','max:30'],
        ]);

        foreach (['bio','specialty'] as $k) {
            if (array_key_exists($k, $data) && is_string($data[$k])) {
                $data[$k] = ['en' => $data[$k]];
            }
        }

 
        $userFields    = ['name','email','phone'];
        $userData      = array_intersect_key($data, array_flip($userFields));
        $therapistData = array_diff_key($data, array_flip($userFields));

        if (!empty($therapistData)) {
            $t->update($therapistData);
        }

        if (!empty($userData)) {
            $r->user()->update($userData);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => $t->fresh('user'),
        ]);
    }

    public function updateAvatar(Request $r)
    {
        $t = $r->user()->therapist()->firstOrFail();
        $user = $r->user();

        $data = $r->validate([
            'avatar' => ['required','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ]);

        $avatarPath = $r->file('avatar')->store('avatars/doctors', 'public');

        $user->update([
            'avatar' => $avatarPath,
        ]);

        return response()->json([
            'message' => 'Avatar updated successfully.',
            'data'    => $t->fresh('user'), 
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
}
