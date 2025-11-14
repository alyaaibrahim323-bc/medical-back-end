<?php
// app/Mail/SessionLinkMail.php
namespace App\Mail;

use App\Models\TherapySession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SessionLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TherapySession $session,
        public string $joinUrl
    ) {}

    public function build()
    {
        return $this->subject('Your Therapy Session Link')
            ->view('emails.session_link'); // هنضيفه تحت
    }
}
