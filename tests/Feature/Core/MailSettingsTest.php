<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['email' => 'admin@test.dev']);
    }

    /**
     * @param  array<string, mixed>  $mail
     * @return array<string, mixed>
     */
    private function payload(array $mail): array
    {
        return array_merge([
            'mode' => 'claro',
            'color' => 'azul',
        ], $mail);
    }

    public function test_editor_cannot_touch_mail_settings(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('admin.configuracoes.index'))->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.configuracoes.update'), $this->payload([
                'mail_transport' => 'smtp',
                'mail_host' => 'mail.x.com',
                'mail_from_address' => 'a@x.com',
            ]))
            ->assertForbidden();

        $this->actingAs($editor)
            ->post(route('admin.configuracoes.email.teste'))
            ->assertForbidden();

        $this->assertNull(Setting::query()->where('key', 'mail_host')->first());
    }

    public function test_admin_saves_smtp_and_it_applies_to_the_mail_config(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), $this->payload([
                'mail_transport' => 'smtp',
                'mail_host' => 'smtp.exemplo.com',
                'mail_port' => '465',
                'mail_encryption' => 'ssl',
                'mail_username' => 'user@exemplo.com',
                'mail_password' => 'senha-super-secreta',
                'mail_from_address' => 'site@exemplo.com',
                'mail_from_name' => 'Meu Site',
            ]))
            ->assertRedirect(route('admin.configuracoes.index'))
            ->assertSessionHas('success');

        $mail = app(MailSettings::class);
        $this->assertTrue($mail->smtpConfigured());

        // Runtime config overwritten — what every Mail:: send will use.
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.exemplo.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
        $this->assertSame('user@exemplo.com', config('mail.mailers.smtp.username'));
        $this->assertSame('senha-super-secreta', config('mail.mailers.smtp.password'));
        $this->assertSame('site@exemplo.com', config('mail.from.address'));
        $this->assertSame('Meu Site', config('mail.from.name'));
    }

    public function test_password_is_encrypted_at_rest_and_form_exposes_only_a_flag(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), $this->payload([
                'mail_transport' => 'smtp',
                'mail_host' => 'smtp.exemplo.com',
                'mail_from_address' => 'site@exemplo.com',
                'mail_password' => 'senha-super-secreta',
            ]))
            ->assertRedirect();

        $stored = (string) Setting::query()->where('key', 'mail_password')->first()?->value;

        $this->assertNotSame('senha-super-secreta', $stored);
        $this->assertStringNotContainsString('senha-super-secreta', $stored);
        $this->assertSame('senha-super-secreta', Crypt::decryptString($stored));

        $html = $this->actingAs($admin)->get(route('admin.configuracoes.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('senha-super-secreta', $html);
        $this->assertStringContainsString(__('settings.mail_password_stored'), $html);
    }

    public function test_blank_password_keeps_and_clear_flag_removes(): void
    {
        $admin = $this->admin();
        $base = [
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.exemplo.com',
            'mail_from_address' => 'site@exemplo.com',
        ];

        $this->actingAs($admin)->post(route('admin.configuracoes.update'), $this->payload($base + ['mail_password' => 'segredo-1']));
        $this->assertSame('segredo-1', app(MailSettings::class)->decryptedPassword());

        // Saving without touching the password field keeps it.
        $this->actingAs($admin)->post(route('admin.configuracoes.update'), $this->payload($base + ['mail_password' => '']));
        $this->assertSame('segredo-1', app(MailSettings::class)->decryptedPassword());

        // The clear flag removes it.
        $this->actingAs($admin)->post(route('admin.configuracoes.update'), $this->payload($base + ['mail_password_clear' => '1']));
        $this->assertNull(app(MailSettings::class)->decryptedPassword());
    }

    public function test_smtp_requires_host_and_from_address(): void
    {
        $admin = $this->admin();

        // The real form always posts every mail field; empty strings mean
        // "present but unset" — exactly what required_if must reject.
        $this->actingAs($admin)
            ->post(route('admin.configuracoes.update'), $this->payload([
                'mail_transport' => 'smtp',
                'mail_host' => '',
                'mail_port' => '587',
                'mail_encryption' => 'tls',
                'mail_username' => '',
                'mail_password' => '',
                'mail_from_address' => '',
                'mail_from_name' => '',
            ]))
            ->assertSessionHasErrors(['mail_host', 'mail_from_address']);

        $this->assertSame('log', app(MailSettings::class)->transport()); // nothing saved (validation halted)
    }

    public function test_log_transport_never_delivers_and_clears_the_override(): void
    {
        $admin = $this->admin();
        $base = [
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.exemplo.com',
            'mail_from_address' => 'site@exemplo.com',
        ];
        $this->actingAs($admin)->post(route('admin.configuracoes.update'), $this->payload($base));

        $this->actingAs($admin)->post(route('admin.configuracoes.update'), $this->payload([
            'mail_transport' => 'log',
            'mail_host' => '',
            'mail_from_address' => '',
        ]));

        $this->assertFalse(app(MailSettings::class)->smtpConfigured());
        $this->assertSame('log', config('mail.default'));
    }

    public function test_the_applied_override_survives_into_the_next_request(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.configuracoes.update'), $this->payload([
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.proximo.dev',
            'mail_from_address' => 'nova@proximo.dev',
        ]));

        // Fresh request: the provider's boot hook must re-apply stored settings.
        $this->actingAs($admin)->get(route('admin.configuracoes.index'))->assertOk();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.proximo.dev', config('mail.mailers.smtp.host'));
    }

    public function test_the_test_button_sends_through_the_current_config(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.configuracoes.update'), $this->payload([
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.exemplo.com',
            'mail_from_address' => 'site@exemplo.com',
        ]));

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.email.teste'))
            ->assertRedirect()
            ->assertSessionHas('success', __('settings.mail_test_sent', ['address' => 'admin@test.dev']));

        Mail::assertSentCount(1);
    }

    public function test_the_test_button_refuses_when_smtp_is_not_configured(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.email.teste'))
            ->assertRedirect()
            ->assertSessionHas('error', __('settings.mail_test_unconfigured'));

        Mail::assertNothingSent();
    }

    public function test_the_test_button_is_rate_limited(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.configuracoes.update'), $this->payload([
            'mail_transport' => 'smtp',
            'mail_host' => 'smtp.exemplo.com',
            'mail_from_address' => 'site@exemplo.com',
        ]));

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($admin)->post(route('admin.configuracoes.email.teste'));
        }

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.email.teste'))
            ->assertSessionHas('error');
    }

    public function test_the_settings_page_renders_the_mail_section(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.configuracoes.index'))
            ->assertOk()
            ->assertSee(__('settings.mail'))
            ->assertSee(__('settings.mail_transport'))
            ->assertSee(__('settings.mail_test'), false)
            ->assertSee(route('admin.configuracoes.email.teste'), false);
    }
}
