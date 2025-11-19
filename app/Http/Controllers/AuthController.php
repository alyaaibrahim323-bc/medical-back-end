<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\EmailOtp;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as Pw;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ========= Register/Login/Me/Logout =========

    public function register(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','unique:users,email'],
            'password' => ['required', Pw::min(8)],
            'preferred_locale' => ['nullable','in:en,ar'],
        ]);

        $u = User::create([
            'name' => $data['name'],
            'email'=> $data['email'],
            'password'=> Hash::make($data['password']),
            'role' => 'user',
            'status'=>'active',
            'phone' => $data['phone'] ?? null,
            'preferred_locale' => $data['preferred_locale'] ?? 'en',
        ]);
        $u->assignRole('user');

        // Generate + store OTP
        $otp = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expiresMinutes = (int) config('auth.otp_expires', (int)env('AUTH_OTP_EXPIRES', 10));

        EmailOtp::updateOrCreate(
            ['user_id' => $u->id],
            [
                'code_hash'   => Hash::make($otp),
                'attempts'    => 0,
                'expires_at'  => now()->addMinutes($expiresMinutes),
                'last_sent_at'=> now(),
                'consumed_at' => null,
            ]
        );

        // Send OTP email via Mailable
        Mail::to($u->email)->send(new OtpMail($u, $otp, $expiresMinutes));

        // مفيش login هنا – لازم يفعّل الأول
        return response()->json([
            'message' => 'Registered successfully. Please verify your email with the OTP sent to you.',
            'data'=>[
                'email' => $u->email,
                'email_verification'=>[
                    'method'=>'otp',
                    'expires_minutes'=>$expiresMinutes
                ]
            ]
        ], 201);
    }

    public function login(Request $r)
    {
        $data = $r->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        $u = User::where('email', $data['email'])->first();

        if (!$u) {
            return response()->json(['message'=>'No account found for this email'], 422);
        }

        if (!Hash::check($data['password'], $u->password)) {
            return response()->json(['message'=>'Incorrect password'], 422);
        }

        // لازم الإيميل يكون متفعّل
        if (is_null($u->email_verified_at)) {
            return response()->json([
                'message' => 'Please verify your email before logging in',
                'code'    => 'email_not_verified',
            ], 403);
        }

        Auth::login($u);
        $token = $u->createToken('api')->plainTextToken;

        return response()->json([
            'data'=>[
                'user'=>$u,
                'token'=>$token
            ]
        ]);
    }

    public function me(Request $r)
    {
        return response()->json(['data'=>$r->user()]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();
        return response()->json(['message'=>'Logged out']);
    }

    // ========= Email Verify via OTP =========

    public function verifyEmailOtp(Request $r)
    {
        $data = $r->validate([
            'code'  => ['required','digits:4'],
            'email' => ['required','email','exists:users,email'],
        ]);

        $u = User::where('email', $data['email'])->firstOrFail();

        if ($u->email_verified_at) {
            return response()->json(['message'=>'Already verified']);
        }

        $rec = EmailOtp::where('user_id', $u->id)->first();
        if (!$rec) return response()->json(['message'=>'No OTP found'], 422);
        if ($rec->consumed_at) return response()->json(['message'=>'OTP already used'], 422);
        if (now()->greaterThan($rec->expires_at)) return response()->json(['message'=>'OTP expired'], 422);

        $maxAttempts = (int) config('auth.otp_max_attempts', (int)env('AUTH_OTP_MAX_ATTEMPTS', 5));
        if ($rec->attempts >= $maxAttempts) {
            return response()->json(['message'=>'Too many attempts'], 429);
        }

        $rec->attempts += 1;
        $rec->save();

        if (!Hash::check($data['code'], $rec->code_hash)) {
            return response()->json(['message'=>'Invalid code'], 422);
        }

        // success
        $u->forceFill(['email_verified_at'=>now()])->save();
        $rec->update(['consumed_at'=>now()]);

        return response()->json(['message'=>'Email verified successfully']);
    }

    public function resendEmailOtp(Request $r)
    {
        $data = $r->validate([
            'email' => ['required','email','exists:users,email'],
        ]);

        $u = User::where('email', $data['email'])->firstOrFail();

        if ($u->email_verified_at) {
            return response()->json(['message'=>'Already verified']);
        }

        $rec = EmailOtp::firstOrNew(['user_id'=>$u->id]);
        $cooldown = (int) config('auth.otp_resend_cooldown', (int)env('AUTH_OTP_RESEND_COOLDOWN', 60));

        if ($rec->last_sent_at && $rec->last_sent_at->diffInSeconds(now()) < $cooldown) {
            $wait = $cooldown - $rec->last_sent_at->diffInSeconds(now());
            return response()->json(['message'=>"Try again in {$wait}s"], 429);
        }

        $otp = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expiresMinutes = (int) config('auth.otp_expires', (int)env('AUTH_OTP_EXPIRES', 10));

        $rec->code_hash   = Hash::make($otp);
        $rec->attempts    = 0;
        $rec->expires_at  = now()->addMinutes($expiresMinutes);
        $rec->last_sent_at= now();
        $rec->consumed_at = null;
        $rec->save();

        Mail::to($u->email)->send(new OtpMail($u, $otp, $expiresMinutes));

        return response()->json(['message'=>'OTP sent']);
    }

    // ========= Forgot / Reset (LINK to app) =========

    public function sendResetLink(Request $r)
    {
        $data = $r->validate(['email'=>['required','email','exists:users,email']]);
        $st = Password::sendResetLink(['email'=>$data['email']]);
        return $st === Password::RESET_LINK_SENT
            ? response()->json(['message'=>__($st)])
            : response()->json(['message'=>__($st)], 422);
    }

    public function resetPassword(Request $r)
    {
        $data = $r->validate([
            'email' => ['required','email','exists:users,email'],
            'token' => ['required','string'],
            'password' => ['required','string','min:8','confirmed'],
        ]);

        $st = Password::reset($data, function (User $u, string $password) {
            $u->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            event(new PasswordReset($u));
        });

        return $st === Password::PASSWORD_RESET
            ? response()->json(['message'=>__($st)])
            : response()->json(['message'=>__($st)], 422);
    }
}
