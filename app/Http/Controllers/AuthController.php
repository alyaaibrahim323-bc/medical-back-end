<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\EmailOtp;
use App\Models\RefreshToken;
use App\Mail\OtpMail;
use App\Services\GeoIpService;
use App\Services\GeoPricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as Pw;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;



class AuthController extends Controller
{


    public function register(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','unique:users,email'],
            'password' => ['required', Pw::min(8)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'user',
            'status'   => 'active',
        ]);

        $otp = $this->generateOtp($user, 'email_verify');

        Mail::to($user->email)->send(
            new OtpMail($user, $otp['code'], $otp['expires'])
        );

        return response()->json([
            'message' => 'Registered successfully. Please verify your email.',
            'data' => [
                'email' => $user->email
            ]
        ], 201);
    }



    public function login(Request $r)
    {
        $data = $r->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        $user = User::where('email',$data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message'=>'Invalid credentials'],422);
        }

        if (!$user->email_verified_at) {

            $otp = $this->generateOtp($user, 'email_verify');

            // لو مش throttled ابعت ايميل
            if (!($otp['throttled'] ?? false)) {
                Mail::to($user->email)->send(
                    new OtpMail($user, $otp['code'], $otp['expires'])
                );
            }

            return response()->json([
                'message' => ($otp['throttled'] ?? false)
                    ? 'Email not verified. OTP already sent recently.'
                    : 'Email not verified. OTP sent.',
                'code'   => 'email_not_verified',
                'action' => 'verify_email',
                'data'   => [
            'email' => $user->email,
            'expires_in_minutes' => $otp['expires'],
            'retry_after_seconds' => $otp['retry_after_seconds'] ?? null,
        ],

            ], 403);
        }

        try {
            $geo = app(GeoIpService::class);

            $ip = $geo->clientIp($r);
            $country = $geo->detectCountryFromIp($ip); // EG / US / null
            $map = $geo->regionAndCurrency($country);

            $shouldUpdate =
                !$user->pricing_region ||
                !$user->geo_detected_at ||
                $user->geo_detected_at->lt(now()->subDays(7));

            // ✅ مهم: لو country null ما نكتبش null في DB
            if ($shouldUpdate && $country) {
                $user->forceFill([
                    'country_code'    => $country,
                    'pricing_region'  => $map['region'], // EG_LOCAL / INTL
                    'geo_detected_at' => now(),
                ])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('GEOIP_LOGIN_FAILED', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }



        Auth::login($user);

        return response()->json([
            'data' => $this->issueTokens($user)
        ]);
    }

    /* =========================================================
        VERIFY EMAIL OTP
    ========================================================= */

    public function verifyEmailOtp(Request $r)
    {
        return $this->verifyOtp(
            $r,
            'email_verify',
            function (User $user) {
                $user->update(['email_verified_at'=>now()]);
            }
        );
    }

    public function resendEmailOtp(Request $r)
    {
        $data = $r->validate([
            'email'=>['required','email','exists:users,email'],
        ]);

        $user = User::where('email',$data['email'])->firstOrFail();

        if ($user->email_verified_at) {
            return response()->json(['message'=>'Already verified']);
        }

       $otp = $this->generateOtp($user,'email_verify');

        if (!($otp['throttled'] ?? false)) {
            Mail::to($user->email)->send(new OtpMail($user, $otp['code'], $otp['expires']));
        }

        return response()->json([
            'message' => ($otp['throttled'] ?? false) ? 'OTP already sent recently' : 'OTP sent',
            'data' => [
                'email' => $user->email,
                'expires_in_minutes' => $otp['expires'],
                'retry_after_seconds' => $otp['retry_after_seconds'] ?? null,
            ]
        ]);

    }


    public function sendPasswordOtp(Request $r)
    {
        $data = $r->validate([
            'email'=>['required','email','exists:users,email'],
        ]);

        $user = User::where('email',$data['email'])->firstOrFail();

        $otp = $this->generateOtp($user,'password_reset');

        Mail::to($user->email)->send(
            new OtpMail($user,$otp['code'],$otp['expires'])
        );

        return response()->json(['message'=>'OTP sent']);
    }

    public function resetPasswordWithOtp(Request $r)
    {
        return $this->verifyOtp(
            $r,
            'password_reset',
            function (User $user, array $data) {
                $user->tokens()->delete();

                $user->update([
                    'password' => Hash::make($data['password']),
                    'remember_token' => Str::random(60),
                ]);
            },
            true
        );
    }



    public function refresh(Request $r)
    {
        $data = $r->validate([
            'refresh_token'=>['required','string'],
        ]);

        $hash = hash('sha256',$data['refresh_token']);

        $rec = RefreshToken::where('token_hash',$hash)->first();

        if (!$rec || $rec->revoked_at || now()->gt($rec->expires_at)) {
            return response()->json(['message'=>'Invalid refresh token'],401);
        }

        $user = User::find($rec->user_id);
        if (!$user) {
            return response()->json(['message'=>'User not found'],401);
        }

        $rec->update(['revoked_at'=>now()]);

        return response()->json([
            'data' => $this->issueTokens($user)
        ]);
    }



    public function logout(Request $r)
    {
        $data = $r->validate([
            'refresh_token'=>['nullable','string'],
        ]);

        $r->user()->currentAccessToken()?->delete();

        if (!empty($data['refresh_token'])) {
            RefreshToken::where(
                'token_hash',
                hash('sha256',$data['refresh_token'])
            )->update(['revoked_at'=>now()]);
        }

        return response()->json(['message'=>'Logged out']);
    }



 private function generateOtp(User $user, string $purpose): array
{
    $expiresMinutes = (int) config('auth.otp_expires', 10);
    $cooldownSeconds = 60;

    $existing = EmailOtp::where('user_id', $user->id)
        ->where('purpose', $purpose)
        ->first();

    // ✅ لو مفيش سجل أو OTP اتستهلك أو انتهى -> لازم نولّد جديد
    if (
        !$existing ||
        $existing->consumed_at ||
        !$existing->expires_at ||
        now()->gt($existing->expires_at)
    ) {
        return $this->forceGenerateOtp($user, $purpose, $expiresMinutes);
    }

    // ✅ OTP لسه صالح: لو اتبعت قريب -> Throttle
    if (
        $existing->last_sent_at &&
        now()->diffInSeconds($existing->last_sent_at) < $cooldownSeconds
    ) {
        $diff = now()->diffInSeconds($existing->last_sent_at);

        return [
            'code' => null,
            'expires' => $expiresMinutes,
            'throttled' => true,
            'retry_after_seconds' => $cooldownSeconds - $diff,
        ];
    }

    // ✅ صالح لكن مش قريب: ابعتي جديد
    return $this->forceGenerateOtp($user, $purpose, $expiresMinutes);
}

private function forceGenerateOtp(User $user, string $purpose, int $expiresMinutes): array
{
    $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

    EmailOtp::updateOrCreate(
        [
            'user_id' => $user->id,
            'purpose' => $purpose,
        ],
        [
            'code_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresMinutes),
            'last_sent_at' => now(),
            'consumed_at' => null,
        ]
    );

    return [
        'code' => $otp,
        'expires' => $expiresMinutes,
        'throttled' => false,
        'retry_after_seconds' => 0,
    ];
}



    private function verifyOtp(
        Request $r,
        string $purpose,
        callable $onSuccess,
        bool $withPassword=false
    ) {
        $rules = [
            'email'=>['required','email','exists:users,email'],
            'code'=>['required','digits:4'],
        ];

        if ($withPassword) {
            $rules['password'] = ['required','min:8','confirmed'];
        }

        $data = $r->validate($rules);

        $user = User::where('email',$data['email'])->firstOrFail();

        $otp = EmailOtp::where('user_id',$user->id)
            ->where('purpose',$purpose)
            ->first();

        if (!$otp || $otp->consumed_at || now()->gt($otp->expires_at)) {
            return response()->json(['message'=>'OTP expired or invalid'],422);
        }

        if (!Hash::check($data['code'],$otp->code_hash)) {
            $otp->increment('attempts');
            return response()->json(['message'=>'Invalid OTP'],422);
        }

        $onSuccess($user,$data);

        $otp->update(['consumed_at'=>now()]);

        return response()->json([
            'message'=>'Success',
            'data'=>$this->issueTokens($user)
        ]);
    }

    private function issueTokens(User $user): array
    {
        $access = $user->createToken('access')->plainTextToken;

        $plainRefresh = Str::random(64);

        RefreshToken::create([
            'user_id'=>$user->id,
            'token_hash'=>hash('sha256',$plainRefresh),
            'expires_at'=>now()->addDays(30),
        ]);

        return [
            'user'=>$user,
            'access_token'=>$access,
            'refresh_token'=>$plainRefresh,
            'token_type'=>'Bearer',
        ];
    }

    public function sendDashboardResetToken(Request $r)
{
    $data = $r->validate([
        'email' => ['required','email','exists:users,email'],
    ]);

    $user = User::where('email', $data['email'])->firstOrFail();

    $token = Password::createToken($user);

    return response()->json([
        'message' => 'Reset token generated',
        'data' => [
            'email' => $user->email,
            'token' => $token,

        ]
    ]);
}

public function resetDashboardPassword(Request $r)
{
    $data = $r->validate([
        'email'    => ['required','email','exists:users,email'],
        'token'    => ['required','string'],
        'password' => ['required','confirmed', Pw::min(8)],
    ]);

    $status = Password::reset(
        $data,
        function (User $user, string $password) {
            $user->tokens()->delete();

            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        }
    );

    if ($status !== Password::PASSWORD_RESET) {
        return response()->json([
            'message' => __($status),
        ], 422);
    }

    return response()->json([
        'message' => 'Password reset successfully',
    ]);
}

}
