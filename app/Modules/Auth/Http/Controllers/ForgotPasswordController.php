<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use App\Modules\Auth\Mail\ResetPasswordLink;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * "Forgot password": e-mail link flow (Laravel password broker), guest routes
 * under the dynamic admin path — exactly where the login lives. The success
 * response is identical whether or not the e-mail exists (no account
 * probing); deliveries go through the panel-managed SMTP (MailSettings
 * override). The reset token travels as a QUERY parameter: the broker's token
 * is a bcrypt hash containing "/", which breaks {token} path segments on
 * nginx/Apache.
 */
class ForgotPasswordController extends Controller
{
    /**
     * GET {admin}/esqueci-senha — the e-mail form.
     */
    public function create(): View
    {
        return view('auth::forgot');
    }

    /**
     * POST {admin}/esqueci-senha — issue a token and mail the reset link.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:190']]);

        $user = User::query()->where('email', $request->input('email'))->where('active', true)->first();

        if ($user !== null) {
            $token = Password::broker()->createToken($user);

            Mail::to($user->email)->send(new ResetPasswordLink(
                // route() URL-encodes query values; the bcrypt token contains
                // "/" and "+" — they survive as %2F/%2B on the wire.
                resetUrl: route('password.reset', ['token' => $token, 'email' => $user->email]),
                visitorName: (string) $user->name,
            ));
        }

        // Same answer for unknown/inactive e-mails — never confirm an account exists.
        return back()->with('status', __('login.forgot_sent'));
    }

    /**
     * GET {admin}/redefinir-senha?token=…&email=… — the new-password form.
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $email = (string) $request->input('email');
        $token = (string) $request->input('token');

        $user = User::query()->where('email', $email)->first();

        if ($user === null || $token === '' || ! Password::broker()->tokenExists($user, $token)) {
            return redirect()->route('password.request')->withErrors(['email' => __('passwords.token')]);
        }

        return view('auth::reset', ['token' => $token, 'email' => $email]);
    }

    /**
     * POST {admin}/redefinir-senha — consume the token and set the password.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput()->withErrors(['email' => [__($status)]]);
        }

        $user = User::query()->where('email', $request->input('email'))->where('active', true)->first();

        if ($user !== null) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('admin.dashboard')->with('status', __('login.reset_success'));
    }
}
