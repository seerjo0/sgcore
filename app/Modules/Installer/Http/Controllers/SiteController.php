<?php

namespace App\Modules\Installer\Http\Controllers;

use App\Modules\Core\Services\EnvironmentWriter;
use App\Modules\Core\Services\InstallerService;
use App\Modules\Core\Services\Settings;
use App\Modules\Installer\Http\InteractsWithInstallerLocale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Throwable;

class SiteController extends Controller
{
    use InteractsWithInstallerLocale;

    public function __construct(
        private InstallerService $installer,
        private EnvironmentWriter $env,
    ) {}

    public function index(Request $request): View
    {
        return $this->form($request, new MessageBag);
    }

    public function store(Request $request): View
    {
        $lang = $this->resolveWizardLocale($request);
        $locale = app()->getLocale();

        $validator = Validator::make($request->all(), [
            'site_name' => ['required', 'string', 'max:120'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:190'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'sample_content' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->form($request, $validator->errors(), null, $lang);
        }

        try {
            $this->installer->install([
                'site_name' => (string) $request->input('site_name'),
                'locale' => $locale,
                'admin' => [
                    'name' => (string) $request->input('admin_name'),
                    'email' => (string) $request->input('admin_email'),
                    'password' => (string) $request->input('admin_password'),
                ],
                'sample_content' => $request->boolean('sample_content'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->form($request, new MessageBag, __('installer.failure_install', ['message' => $exception->getMessage()]), $lang);
        }

        try {
            $this->env->write([
                'APP_LOCALE' => $locale,
                'APP_FALLBACK_LOCALE' => $locale,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return view('installer::done', [
            'siteName' => (string) $request->input('site_name'),
            'lang' => $lang,
        ]);
    }

    public function done(Settings $settings, Request $request): View
    {
        $lang = $this->resolveWizardLocale($request);

        return view('installer::done', [
            'siteName' => (string) $settings->get('site_name', config('app.name')),
            'lang' => $lang,
        ]);
    }

    private function form(Request $request, MessageBag $errors, ?string $failure = null, ?string $lang = null): View
    {
        return view('installer::site', [
            'step' => 3,
            'lang' => $lang ?? $this->resolveWizardLocale($request),
            'values' => $request->only(['site_name', 'admin_name', 'admin_email', 'sample_content']),
            'errors' => $errors,
            'failure' => $failure,
        ]);
    }
}
