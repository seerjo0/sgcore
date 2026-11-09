<?php

namespace App\Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A public contact-form submission, delivered to the configured recipient with
 * the visitor as Reply-To. Body is escaped line-by-line (no HTML from input).
 */
class ContactReceived extends Mailable
{
    public function __construct(
        public string $visitorName,
        public string $visitorEmail,
        public string $visitorMessage,
        public string $mailSubject,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            // Illuminate Mailables Address (public props) — not the Symfony
            // Mime Address (private), which breaks real rendering. Mail::fake
            // does not render, so only a live (log-driver) delivery catches
            // this kind of mistake.
            replyTo: [new Address($this->visitorEmail, $this->visitorName)],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body());
    }

    private function body(): string
    {
        return '<p><strong>'.e(__('site.form_name')).':</strong> '.e($this->visitorName).'</p>'
            .'<p><strong>'.e(__('site.form_email')).':</strong> '.e($this->visitorEmail).'</p>'
            .'<hr><p style="white-space:pre-wrap">'.e($this->visitorMessage).'</p>';
    }
}
