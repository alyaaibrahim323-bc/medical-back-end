@component('mail::message')

{{-- HEADER LOGO --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')])
<img src="{{ asset('images/logo.png') }}"
     alt="Bondwell"
     style="height: 60px;">
@endcomponent
@endslot

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

@endcomponent
