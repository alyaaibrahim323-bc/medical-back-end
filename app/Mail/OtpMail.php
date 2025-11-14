<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $otp;
    public int $expires;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $otp, int $expires)
    {
        $this->user    = $user;
        $this->otp     = $otp;
        $this->expires = $expires;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Bondwell Verification Code')
                    ->markdown('emails.otp-code')
                    ->with([
                        'user'    => $this->user,
                        'otp'     => $this->otp,
                        'expires' => $this->expires,
                    ]);
    }
}
