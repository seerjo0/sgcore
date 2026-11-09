<?php

namespace App\Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The "test email" the Settings page sends through the current mail config.
 * A plain Mailable (not Mail::raw) so Mail::fake records it in tests and any
 * transport can deliver it.
 */
class MailTest extends Mailable
{
    public function __construct(
        public string $mailSubject,
        public string $mailBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(htmlString: nl2br(e($this->mailBody)));
    }
}
