<?php

namespace App\Modules\Core\Http\Controllers\Site;

use App\Modules\Core\Mail\ContactReceived;
use App\Modules\Core\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

/**
 * POST /contato/enviar — receives a public contact-form submission (the
 * builder's contact_form block) and mails it to the configured recipient
 * through the panel-managed SMTP.
 *
 * Anti-spam layers: route throttle (per IP), CSRF (the page containing the
 * block is never document-cached, so the token is fresh per session) and a
 * honeypot (`website`) — bots that fill it get a FAKE success and nothing is
 * sent.
 */
class ContactFormController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:filter', 'max:190'],
            'mensagem' => ['required', 'string', 'min:5', 'max:5000'],
            // Honeypot: humans never send it (empty string -> null via
            // ConvertEmptyStringsToNull), bots fill it and the check below
            // fakes a success. `max:0` would reject null and break real
            // submissions — only the value check matters.
            'website' => ['nullable', 'string'],
            'assunto' => ['nullable', 'string', 'max:150'],
        ]);

        // Honeypot hit (or max:0 violated): pretend success, send nothing.
        if (trim((string) ($request->input('website', ''))) !== '') {
            return back()->with('enviado', 'contato');
        }

        $recipient = trim((string) ($this->settings->get('mail_to_address')
            ?: $this->settings->get('mail_from_address')
            ?: ''));

        if ($recipient === '') {
            return back()->with('enviado', 'contato_erro');
        }

        $subject = trim((string) ($request->input('assunto') ?? ''));

        Mail::to($recipient)->send(new ContactReceived(
            visitorName: (string) $validated['nome'],
            visitorEmail: (string) $validated['email'],
            visitorMessage: (string) $validated['mensagem'],
            mailSubject: (string) __('site.form_subject_prefix').' '
                .($subject !== '' ? $subject : (string) __('site.form_subject_default')),
        ));

        return back()->with('enviado', 'contato');
    }
}
