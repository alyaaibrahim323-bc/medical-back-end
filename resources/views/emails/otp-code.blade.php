@component('mail::message')

@slot('header')
@component('mail::header', ['url' => config('app.url')])
<img src="{{ asset('image/logo.svg') }}"
     alt="Bondwell"
     style="height:60px; width: 60px; margin:auto; display:block;">
@endcomponent
@endslot

# Email Verification

Hello {{ $user->name ?? 'there' }},

Use 3the verification code below to confirm your email address:

@component('mail::panel')
## {{ $otp }}
@endcomponent

This code will expire in **{{ $expires }} minutes**.

If you did not request this, you can safely ignore this email.

Thanks,
**Bondwell Team**

@endcomponent
