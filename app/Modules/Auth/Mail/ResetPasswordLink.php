<?php

namespace App\Modules\Auth\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The "forgot password" link email. A plain Mailable (not the framework
 * notification) so the body is fully translated and the reset URL points at
 * the dynamic admin path.
 */
class ResetPasswordLink extends Mailable
{
    public function __construct(
        public string $resetUrl,
        public string $visitorName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('login.forgot_mail_subject'));
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body());
    }

    private function body(): string
    {
        $safeUrl = e($this->resetUrl);

        return '<p>'.e(__('login.forgot_mail_hello', ['name' => $this->visitorName]))."</p>\n"
            .'<p>'.e(__('login.forgot_mail_line'))."</p>\n"
            ."<p><a href=\"{$safeUrl}\">".e(__('login.forgot_mail_button')).'</a></p>'."\n"
            .'<p style="color:#64748b;font-size:13px">'.e(__('login.forgot_mail_expiry'))."</p>\n"
            .'<p style="color:#94a3b8;font-size:12px">'.e(__('login.forgot_mail_ignore')).'</p>';
    }
}
