<?php

namespace App\Modules\Installer\Http\Controllers;

use App\Modules\Core\Exceptions\DatabaseSetupException;
use App\Modules\Core\Services\DatabaseSetup;
use App\Modules\Core\Services\EnvironmentWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

class DatabaseController extends Controller
{
    public function __construct(
        private DatabaseSetup $database,
        private EnvironmentWriter $env,
    ) {}

    public function index(Request $request): View
    {
        return view('installer::database', [
            'step' => 2,
            'values' => [
                'host' => $request->input('host', config('database.connections.mysql.host', '127.0.0.1')),
                'port' => $request->input('port', config('database.connections.mysql.port', 3306)),
                'database' => $request->input('database', config('database.connections.mysql.database', 'sgcore')),
                'username' => $request->input('username', config('database.connections.mysql.username', 'root')),
            ],
            'errors' => new MessageBag,
            'failure' => null,
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+$/', 'max:64'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->form($request, $validator->errors());
        }

        try {
            $this->database->testConnection(
                (string) $request->input('host'),
                (int) $request->input('port'),
                (string) $request->input('database'),
                (string) $request->input('username'),
                (string) $request->input('password', ''),
            );
        } catch (DatabaseSetupException $exception) {
            return $this->form($request, new MessageBag, $exception->getMessage());
        }

        try {
            $this->env->write([
                'APP_NAME' => 'sgcore',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) $request->input('host'),
                'DB_PORT' => (string) $request->input('port'),
                'DB_DATABASE' => (string) $request->input('database'),
                'DB_USERNAME' => (string) $request->input('username'),
                'DB_PASSWORD' => (string) $request->input('password', ''),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->form($request, new MessageBag, $exception->getMessage());
        }

        return redirect()->route('installer.site');
    }

    private function form(Request $request, MessageBag $errors, ?string $failure = null): View
    {
        return view('installer::database', [
            'step' => 2,
            'values' => $request->only(['host', 'port', 'database', 'username']),
            'errors' => $errors,
            'failure' => $failure,
        ]);
    }
}
