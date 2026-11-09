<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Auth\Mail\ResetPasswordLink;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_the_login_page_links_to_recovery(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(__('login.forgot'))
            ->assertSee(route('password.request'), false);
    }

    public function test_the_forgot_page_is_for_guests_only(): void
    {
        $this->get(route('password.request'))->assertOk();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('password.request'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_known_active_email_gets_a_reset_link_and_probing_stays_blind(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'alvo@teste.dev', 'active' => true]);

        $this->post(route('password.email'), ['email' => 'alvo@teste.dev'])
            ->assertRedirect()
            ->assertSessionHas('status', __('login.forgot_sent'));

        Mail::assertSent(ResetPasswordLink::class, function ($mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && str_contains($mail->resetUrl, '/redefinir-senha')
                && str_contains($mail->resetUrl, 'token=');
        });

        // Unknown e-mail: SAME response, no mail — the form never confirms accounts.
        $this->post(route('password.email'), ['email' => 'ninguem@teste.dev'])
            ->assertSessionHas('status', __('login.forgot_sent'));

        Mail::assertNotSent(function (ResetPasswordLink $mail): bool {
            return $mail->hasTo('ninguem@teste.dev');
        });

        // Inactive user: no mail either.
        User::factory()->create(['email' => 'parado@teste.dev', 'active' => false]);
        $this->post(route('password.email'), ['email' => 'parado@teste.dev'])->assertSessionHas('status', __('login.forgot_sent'));
        Mail::assertNotSent(function (ResetPasswordLink $mail): bool {
            return $mail->hasTo('parado@teste.dev');
        });
    }

    public function test_the_reset_form_opens_with_a_valid_token_and_rejects_a_bogus_one(): void
    {
        $user = User::factory()->create(['email' => 'alvo@teste.dev', 'active' => true]);

        $this->get(route('password.reset', ['token' => 'nao-existe', 'email' => 'alvo@teste.dev']))
            ->assertRedirect(route('password.request'));

        $this->post(route('password.email'), ['email' => 'alvo@teste.dev']);

        $token = Password::broker()->createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'alvo@teste.dev']))
            ->assertOk()
            ->assertSee(__('login.reset_title'));
    }

    public function test_the_password_can_be_reset_and_the_user_is_signed_in(): void
    {
        $user = User::factory()->create(['email' => 'alvo@teste.dev', 'active' => true, 'password' => 'senha-antiga']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'alvo@teste.dev',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', __('login.reset_success'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('senha-nova-123', $user->fresh()->password));

        // Token is consumed — reuse fails. (Sign out first: after the
        // successful reset the user is authenticated; guest routes bounce.)
        $this->post(route('logout'));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'alvo@teste.dev',
            'password' => 'outra-12345',
            'password_confirmation' => 'outra-12345',
        ])->assertSessionHasErrors('email');
    }

    public function test_reset_requires_a_strong_confirmed_password(): void
    {
        $user = User::factory()->create(['email' => 'alvo@teste.dev', 'active' => true]);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'alvo@teste.dev',
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])->assertSessionHasErrors('password');
    }

    public function test_the_mail_to_address_is_saved_for_forms(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.configuracoes.update'), [
            'mode' => 'claro',
            'color' => 'azul',
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.x.dev',
            'mail_port' => '587',
            'mail_encryption' => 'tls',
            'mail_username' => '',
            'mail_password' => '',
            'mail_from_address' => 'no-reply@x.dev',
            'mail_from_name' => '',
            'mail_to_address' => 'chefe@x.dev',
        ])->assertRedirect();

        $this->assertSame('chefe@x.dev', app(MailSettings::class)->form()['to_address']);
        $this->assertSame('chefe@x.dev', Setting::query()->where('key', 'mail_to_address')->first()?->value);
    }
}
