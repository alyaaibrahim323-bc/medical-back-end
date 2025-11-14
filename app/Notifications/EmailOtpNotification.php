<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code, public int $expiresMinutes = 10) {}

    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(__('Verify your email'))
            ->line(__('Your verification code is: :code', ['code'=>$this->code]))
            ->line(__('This code expires in :min minutes.', ['min'=>$this->expiresMinutes]));
    }
}
