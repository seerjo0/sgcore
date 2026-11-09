<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Modules\Core\Mail\MailTest;
use App\Modules\Core\Services\AdminPreferences;
use App\Modules\Core\Services\LocalePreferences;
use App\Modules\Core\Services\MailSettings;
use App\Modules\Core\Services\Settings;
use App\Modules\Media\Models\Medium;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private AdminPreferences $preferences,
        private LocalePreferences $locales,
        private Settings $settings,
        private MailSettings $mail,
    ) {}

    /**
     * GET /admin/configuracoes — admin preferences + site/SEO defaults + SMTP.
     */
    public function index(): View
    {
        return view('core::admin.settings.index', [
            'modes' => $this->preferences->modes(),
            'colors' => $this->preferences->colors(),
            'currentMode' => $this->preferences->mode(),
            'currentColor' => $this->preferences->color(),
            'adminPath' => $this->preferences->adminPath(),
            'siteName' => (string) $this->settings->get('site_name', config('app.name')),
            'siteLogoId' => (string) ($this->settings->get('site_logo') ?? ''),
            'siteLogoUrl' => $this->mediaUrl((string) ($this->settings->get('site_logo') ?? '')),
            'faviconId' => (string) ($this->settings->get('site_favicon') ?? ''),
            'faviconUrl' => $this->mediaUrl((string) ($this->settings->get('site_favicon') ?? '')),
            'seoTitle' => (string) $this->settings->get('seo_title', ''),
            'seoDescription' => (string) $this->settings->get('seo_description', ''),
            'seoOgImageId' => (string) ($this->settings->get('seo_og_image') ?? ''),
            'seoOgImageUrl' => $this->mediaUrl((string) ($this->settings->get('seo_og_image') ?? '')),
            'locales' => $this->locales->available(),
            'currentLocale' => $this->locales->locale(),
            'mail' => $this->mail->form(),
            'mailReady' => $this->mail->smtpConfigured(),
            'mailTestAddress' => (string) (auth()->user()?->email ?? ''),
        ]);
    }

    /**
     * POST /admin/configuracoes — persist admin preferences and site/SEO defaults.
     *
     * Site/SEO fields use `sometimes` so older clients posting only mode/color
     * keep working; every submitted key is saved (empty media/text clears to null).
     */
    public function update(Request $request): RedirectResponse
    {
        if ($request->has('admin_path')) {
            $request->merge(['admin_path' => strtolower(trim((string) $request->input('admin_path')))]);
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:'.implode(',', array_keys($this->preferences->modes()))],
            'color' => ['required', 'string', 'in:'.implode(',', array_keys($this->preferences->colors()))],
            'site_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'site_logo' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'site_favicon' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:300'],
            'seo_og_image' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'site_locale' => ['sometimes', 'string', 'in:'.implode(',', array_keys($this->locales->available()))],

            // Mail (panel-managed SMTP). Fields are `sometimes` so clients
            // posting only the older sections keep working.
            'mail_transport' => ['sometimes', 'string', 'in:log,smtp'],
            'mail_host' => ['sometimes', 'nullable', 'string', 'max:255', 'required_if:mail_transport,smtp'],
            'mail_port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['sometimes', 'string', 'in:tls,ssl,none'],
            'mail_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_password' => ['sometimes', 'nullable', 'string', 'max:500'],
            'mail_password_clear' => ['sometimes', 'boolean'],
            'mail_from_address' => ['sometimes', 'nullable', 'email', 'max:255', 'required_if:mail_transport,smtp'],
            'mail_from_name' => ['sometimes', 'nullable', 'string', 'max:150'],

            'admin_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                'not_in:'.implode(',', AdminPreferences::RESERVED_PATHS),
            ],
        ], [
            'admin_path.regex' => __('settings.error_path_regex'),
            'admin_path.not_in' => __('settings.error_path_reserved'),
            'admin_path.max' => __('settings.error_path_max'),
        ]);

        $this->settings->set(AdminPreferences::KEY_MODE, $validated['mode'], 'admin');
        $this->settings->set(AdminPreferences::KEY_COLOR, $validated['color'], 'admin');

        $groups = [
            'site_name' => 'general',
            'site_logo' => 'site',
            'site_favicon' => 'site',
            'seo_title' => 'seo',
            'seo_description' => 'seo',
            'seo_og_image' => 'seo',
            'site_locale' => 'general',
            'admin_path' => 'admin',
        ];

        $integers = ['site_logo', 'site_favicon', 'seo_og_image'];

        foreach ($groups as $key => $group) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $value = $validated[$key];

            if ($value === null || $value === '') {
                $value = null;
            } elseif (in_array($key, $integers, true)) {
                $value = (int) $value;
            }

            $this->settings->set($key, $value, $group);
        }

        // Mail fields live in their own service: the password has keep/clear
        // semantics the generic null-coalescing loop must not touch.
        $mailInput = array_intersect_key($validated, array_flip(MailSettings::KEYS));

        if ($mailInput !== []) {
            $this->mail->save(
                $mailInput,
                (bool) ($validated['mail_password_clear'] ?? false),
            );

            // Reflect the new transport immediately in this request's config.
            $this->mail->apply();
        }

        // Let the current request finish in the language just chosen, so
        // the success message already speaks the new locale on the redirect.
        if (array_key_exists('site_locale', $validated)) {
            app()->setLocale($validated['site_locale']);
        }

        // Changing admin_path moves the routes on the next boot, so the
        // redirect must already point at the new (or restored) address —
        // the old URL would 404 right after the save.
        if (array_key_exists('admin_path', $validated)) {
            $path = ($validated['admin_path'] === null || $validated['admin_path'] === '')
                ? $this->preferences->adminPath()
                : $validated['admin_path'];

            return redirect('/'.$path.'/configuracoes')
                ->with('success', __('settings.updated'));
        }

        return redirect()
            ->route('admin.configuracoes.index')
            ->with('success', __('settings.updated'));
    }

    /**
     * POST /admin/configuracoes/email/teste — send a test message through the
     * current mail config to the logged-in user, surfacing SMTP errors verbatim.
     */
    public function testMail(Request $request): RedirectResponse
    {
        $key = 'mail.test:'.($request->user()?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', __('settings.mail_test_throttled', [
                'seconds' => RateLimiter::availableIn($key),
            ]));
        }

        RateLimiter::hit($key, 300);

        if (! $this->mail->smtpConfigured()) {
            return back()->with('error', __('settings.mail_test_unconfigured'));
        }

        $recipient = (string) $request->user()->email;

        try {
            Mail::to($recipient)->send(new MailTest(
                __('settings.mail_test_subject'),
                __('settings.mail_test_body', [
                    'name' => $request->user()->name,
                    'site' => (string) $this->settings->get('site_name', config('app.name')),
                    'transport' => $this->mail->transport(),
                ]),
            ));
        } catch (\Throwable $e) {
            return back()->with('error', __('settings.mail_test_failed', ['message' => $e->getMessage()]));
        }

        return back()->with('success', __('settings.mail_test_sent', ['address' => $recipient]));
    }

    /**
     * Resolve a stored media id to its public URL (empty string when unset/missing).
     */
    private function mediaUrl(string $id): string
    {
        return ctype_digit($id) && $id !== '0'
            ? Medium::query()->find((int) $id)?->url() ?? ''
            : '';
    }
}
