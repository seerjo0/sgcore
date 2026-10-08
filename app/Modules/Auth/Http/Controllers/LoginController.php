<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth::login');
    }

    /**
     * Authenticate an active user.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([...$credentials, 'active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        $inactive = User::query()
            ->where('email', $credentials['email'])
            ->where('active', false)
            ->exists();

        return back()
            ->withErrors([
                'email' => $inactive
                    ? 'Esta conta está inativa. Procure um administrador.'
                    : 'E-mail ou senha inválidos.',
            ])
            ->onlyInput('email');
    }

    /**
     * Log the current user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
