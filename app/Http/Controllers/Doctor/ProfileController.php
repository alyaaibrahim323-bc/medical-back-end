<?php
// app/Http/Controllers/Doctor/ProfileController.php
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
        return response()->json(['data'=>$t]);
    }

   public function update(Request $r)
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
      // الحقول المشتركة
      'name'  => ['sometimes','string','max:100'],
      'email' => ['sometimes','email','max:255'],
      'phone' => ['sometimes','string','max:30'],
    ]);

    // نطبع النصوص في JSON لو محتاج
    foreach (['bio','specialty'] as $k) {
        if (array_key_exists($k, $data) && is_string($data[$k])) {
            $data[$k] = ['en' => $data[$k]];
        }
    }

    // نفصل بيانات user عن بيانات therapist
    $userFields = ['name','email','phone'];
    $userData = array_intersect_key($data, array_flip($userFields));
    $therapistData = array_diff_key($data, array_flip($userFields));

    if ($therapistData) {
        $t->update($therapistData);
    }
    if ($userData) {
        $r->user()->update($userData);
    }

    return response()->json(['data'=>$t->fresh('user')]);
}
public function updatePassword(Request $r)
{
    $user = $r->user(); // دكتور مسجّل دخول

    $data = $r->validate([
'current_password' => ['required','current_password:sanctum'],
        'password' => [
            'required', 'confirmed',
            Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()
        ],
    ]);

    // امنع استخدام نفس الباسورد القديم
    if (Hash::check($data['password'], $user->password)) {
        return response()->json([
            'message' => 'New password must be different from current password.'
        ], 422);
    }

    $user->forceFill([
        'password' => Hash::make($data['password']),
    ])->save();

    // (اختياري) اقفل كل التوكنات القديمة ما عدا الحالي لو بتستخدمي Sanctum
    if (method_exists($user, 'tokens')) {
        $currentTokenId = optional($r->user()->currentAccessToken())->id;
        $user->tokens()
             ->when($currentTokenId, fn($q) => $q->where('id', '!=', $currentTokenId))
             ->delete();
    }

    return response()->json(['message' => 'Password updated successfully.']);
}


}
