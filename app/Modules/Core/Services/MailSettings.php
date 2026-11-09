<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Panel-managed SMTP settings (group 'mail' in the settings table).
 *
 * Values stored here OVERWRITE the .env mail config at runtime (never written
 * back to .env): every mail sent by the app — password reset, form notices,
 * anything Mail:: — resolves config('mail.*') at send time, and apply()
 * patches that config on boot when the site is installed and configured.
 *
 * The SMTP password is stored encrypted (Crypt) and never echoed to the UI;
 * saving an empty password keeps the stored one, `clearPassword` removes it.
 */
class MailSettings
{
    public const KEYS = [
        'mail_transport',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
        'mail_to_address',
    ];

    public function __construct(private Settings $settings, private InstallerLock $lock) {}

    /**
     * Normalize + persist a validated payload. Password rules: empty string
     * keeps the current value; null via clear flag removes it.
     *
     * @param  array<string, mixed>  $input
     */
    public function save(array $input, bool $clearPassword = false): void
    {
        $this->settings->set('mail_transport', $input['mail_transport'] ?? 'log', 'mail');
        $this->settings->set('mail_host', $this->nullable($input['mail_host'] ?? null), 'mail');
        $this->settings->set('mail_port', (int) ($input['mail_port'] ?? 587), 'mail');
        $this->settings->set('mail_encryption', $input['mail_encryption'] ?? 'tls', 'mail');
        $this->settings->set('mail_username', $this->nullable($input['mail_username'] ?? null), 'mail');
        $this->settings->set('mail_from_address', $this->nullable($input['mail_from_address'] ?? null), 'mail');
        $this->settings->set('mail_from_name', $this->nullable($input['mail_from_name'] ?? null), 'mail');
        $this->settings->set('mail_to_address', $this->nullable($input['mail_to_address'] ?? null), 'mail');

        $password = $input['mail_password'] ?? '';

        if ($clearPassword) {
            $this->settings->set('mail_password', null, 'mail');
        } elseif ($password !== '' && $password !== null) {
            $this->settings->set('mail_password', Crypt::encryptString((string) $password), 'mail');
        }
        // else: keep the stored password untouched.
    }

    /**
     * Values for the settings form. The password itself NEVER leaves this
     * service — only whether one is stored.
     *
     * @return array<string, mixed>
     */
    public function form(): array
    {
        return [
            'transport' => (string) ($this->settings->get('mail_transport') ?? 'log'),
            'host' => (string) ($this->settings->get('mail_host') ?? ''),
            'port' => (string) ($this->settings->get('mail_port') ?? '587'),
            'encryption' => (string) ($this->settings->get('mail_encryption') ?? 'tls'),
            'username' => (string) ($this->settings->get('mail_username') ?? ''),
            'from_address' => (string) ($this->settings->get('mail_from_address') ?? ''),
            'from_name' => (string) ($this->settings->get('mail_from_name') ?? ''),
            'to_address' => (string) ($this->settings->get('mail_to_address') ?? ''),
            'has_password' => $this->settings->get('mail_password') !== null,
        ];
    }

    public function smtpConfigured(): bool
    {
        return $this->transport() === 'smtp'
            && (string) ($this->settings->get('mail_host') ?? '') !== ''
            && (string) ($this->settings->get('mail_from_address') ?? '') !== '';
    }

    public function transport(): string
    {
        return (string) ($this->settings->get('mail_transport') ?? 'log');
    }

    /**
     * Patch runtime config('mail.*') from the stored settings. Called on boot
     * (web and CLI) when installed; a missing/corrupt row can never take the
     * site down, so everything is guarded and falls back to .env defaults.
     */
    public function apply(): void
    {
        if (! $this->lock->exists()) {
            return;
        }

        $transport = $this->transport();

        if (! in_array($transport, ['log', 'smtp'], true)) {
            return;
        }

        config(['mail.default' => $transport]);

        if ($transport !== 'smtp') {
            return;
        }

        $from = (string) ($this->settings->get('mail_from_address') ?? '');

        config([
            'mail.mailers.smtp.host' => (string) ($this->settings->get('mail_host') ?? ''),
            'mail.mailers.smtp.port' => (int) ($this->settings->get('mail_port') ?? 587),
            'mail.mailers.smtp.encryption' => (string) ($this->settings->get('mail_encryption') ?? 'tls'),
            'mail.mailers.smtp.username' => $this->settings->get('mail_username'),
            'mail.mailers.smtp.password' => $this->decryptedPassword(),
            'mail.from.address' => $from !== '' ? $from : (string) config('mail.from.address'),
            'mail.from.name' => (string) ($this->settings->get('mail_from_name') ?? '') ?: config('app.name'),
        ]);

        // MailManager caches created mailers; drop them so the next send
        // resolves with the config we just patched.
        try {
            app('mail')->forgetMailers();
        } catch (Throwable) {
            // not resolved yet — nothing cached
        }
    }

    public function decryptedPassword(): ?string
    {
        $stored = $this->settings->get('mail_password');

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            // key rotated / value not encrypted — treat as no password
            return null;
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
