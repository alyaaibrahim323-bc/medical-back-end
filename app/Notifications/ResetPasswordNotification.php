<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as Base;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Base
{
    public function toMail($notifiable)
    {
        $front = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
        $url   = $front.'/reset-password?token='.$this->token.'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject(__('Reset Your Password'))
            ->line(__('Click the button below to reset your password.'))
            ->action(__('Reset Password'), $url)
            ->line(__('If you did not request this, please ignore this email.'));
    }
}
