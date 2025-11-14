@component('mail::message')
# Email Verification

Hello {{ $user->name ?? 'there' }},

Use the verification code below to confirm your email address:

@component('mail::panel')
## {{ $otp }}
@endcomponent

This code will expire in **{{ $expires }} minutes**.

If you did not request this, you can safely ignore this email.

Thanks,
**Bondwell Team**
@endcomponent
