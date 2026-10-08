<?php

namespace App\Modules\Installer\Http\Controllers;

use App\Modules\Core\Services\InstallerService;
use App\Modules\Core\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Throwable;

class SiteController extends Controller
{
    public function __construct(private InstallerService $installer) {}

    public function index(Request $request): View
    {
        return $this->form($request, new MessageBag);
    }

    public function store(Request $request): View
    {
        $validator = Validator::make($request->all(), [
            'site_name' => ['required', 'string', 'max:120'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:190'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'sample_content' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->form($request, $validator->errors());
        }

        try {
            $this->installer->install([
                'site_name' => (string) $request->input('site_name'),
                'admin' => [
                    'name' => (string) $request->input('admin_name'),
                    'email' => (string) $request->input('admin_email'),
                    'password' => (string) $request->input('admin_password'),
                ],
                'sample_content' => $request->boolean('sample_content'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->form($request, new MessageBag, "Falha na instalação: {$exception->getMessage()}");
        }

        return view('installer::done', [
            'siteName' => (string) $request->input('site_name'),
        ]);
    }

    public function done(Settings $settings): View
    {
        return view('installer::done', [
            'siteName' => (string) $settings->get('site_name', config('app.name')),
        ]);
    }

    private function form(Request $request, MessageBag $errors, ?string $failure = null): View
    {
        return view('installer::site', [
            'step' => 3,
            'values' => $request->only(['site_name', 'admin_name', 'admin_email', 'sample_content']),
            'errors' => $errors,
            'failure' => $failure,
        ]);
    }
}
