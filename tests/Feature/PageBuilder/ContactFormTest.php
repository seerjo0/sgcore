<?php

namespace Tests\Feature\PageBuilder;

use App\Models\User;
use App\Modules\Core\Mail\ContactReceived;
use App\Modules\Core\Services\Settings;
use App\Modules\Pages\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The public contact-form block: renders live (uncached), posts to the core
 * endpoint and mails the configured recipient. Reply-to is the visitor.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        Mail::fake();
        app(Settings::class)->set('mail_transport', 'smtp', 'mail');
        app(Settings::class)->set('mail_host', 'smtp.dev', 'mail');
        app(Settings::class)->set('mail_from_address', 'site@meusite.dev', 'mail');
        app(Settings::class)->set('mail_to_address', 'chefe@meusite.dev', 'mail');
    }

    /** Publishes a page whose project is one contact_form node with traits. */
    private function publishFormPage(): Page
    {
        $user = User::factory()->editor()->create();
        $page = Page::query()->create([
            'title' => 'Contato',
            'slug' => 'contato',
            'status' => 'published',
            'content' => ['body' => ''],
        ]);

        $component = [
            'tagName' => 'section',
            'classes' => ['pb-contact-form'],
            'attributes' => [
                'data-pb-type' => 'contact_form',
                'data-pb-title' => 'Fale com a gente',
                'data-pb-button' => 'Disparar',
                'data-pb-subject' => 'Lead do site',
            ],
            'components' => [],
        ];

        $this->actingAs($user)
            ->postJson(route('admin.paginas.builder.publish', $page), [
                'html' => '<section></section>',
                'css' => '.x{}',
                'json' => json_encode(['components' => [$component]]),
            ])
            ->assertOk();

        return $page->fresh();
    }

    private function tokenFrom(string $html): string
    {
        preg_match('/name="_token" value="([^"]+)"/', $html, $m);

        return $m[1] ?? '';
    }

    public function test_the_published_page_renders_a_live_form(): void
    {
        $page = $this->publishFormPage();

        $html = $this->get('/contato')->assertOk()->getContent();

        $this->assertStringContainsString('Fale com a gente', $html);
        $this->assertStringContainsString('action="'.route('site.contato.enviar').'"', $html);
        $this->assertStringContainsString('name="website"', $html); // honeypot
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="assunto" value="Lead do site"', $html);
        $this->assertStringContainsString('Disparar', $html);
    }

    public function test_pages_with_a_form_are_not_document_cached(): void
    {
        $page = $this->publishFormPage();

        $this->get('/contato')->assertOk();

        // Control page WITHOUT a form gets cached; the form page must NOT.
        $plain = Page::query()->create([
            'title' => 'Normal', 'slug' => 'normal', 'status' => 'published', 'content' => ['body' => ''],
            'builder_published' => ['version' => 1, 'html' => '<p>quieto</p>', 'css' => ''],
            'builder_version' => 1,
        ]);
        $this->get('/normal')->assertOk();

        $this->assertNull(Cache::get("pb:doc:{$page->id}:v{$page->builder_version}:p0"), 'página com formulário foi cacheada');
        $this->assertIsString(Cache::get("pb:doc:{$plain->id}:v1:p0"), 'página sem formulário deveria cachear');
    }

    public function test_a_valid_submission_mails_the_configured_recipient(): void
    {
        $page = $this->publishFormPage();
        $html = $this->get('/contato')->getContent();

        $this->post(route('site.contato.enviar'), [
            '_token' => $this->tokenFrom($html),
            'nome' => 'Maria Silva',
            'email' => 'maria@visitante.dev',
            'mensagem' => 'Olá! Quero um orçamento para o projeto.',
            'assunto' => 'Lead do site',
        ])->assertRedirect();

        Mail::assertSent(ContactReceived::class, function (ContactReceived $mail): bool {
            return $mail->hasTo('chefe@meusite.dev')
                && str_contains($mail->envelope()->subject, 'Lead do site')
                && $mail->visitorEmail === 'maria@visitante.dev';
        });
    }

    public function test_the_honeypot_fakes_success_and_sends_nothing(): void
    {
        $page = $this->publishFormPage();
        $html = $this->get('/contato')->getContent();

        $this->post(route('site.contato.enviar'), [
            '_token' => $this->tokenFrom($html),
            'nome' => 'Robô',
            'email' => 'robo@spam.dev',
            'mensagem' => 'Compre meus produtos baratinhos!',
            'website' => 'http://spam.dev',
        ])->assertRedirect();

        Mail::assertNothingSent();
    }

    public function test_validation_errors_come_back_on_the_form(): void
    {
        $page = $this->publishFormPage();
        $html = $this->get('/contato')->getContent();

        // One POST, one inspection: assertSessionHasErrors CONSUMES the flash
        // (it leaves _flash.old as-is), so a second request after it would
        // legitimately show no errors. Verify the redirect carries the right
        // status + flashed old input instead.
        $this->post(route('site.contato.enviar'), [
            '_token' => $this->tokenFrom($html),
            'nome' => '',
            'email' => 'nao-e-email',
            'mensagem' => 'oi',
        ])
            ->assertRedirect('/contato')
            ->assertSessionHasErrors(['nome', 'email', 'mensagem'])
            ->assertSessionHas('_old_input');

        Mail::assertNothingSent();
    }

    public function test_without_a_destination_the_form_reports_failure(): void
    {
        // forget() (not a raw query delete) — Settings caches values per
        // instance and the controller resolves it through the container.
        app(Settings::class)->forget('mail_to_address');
        app(Settings::class)->forget('mail_from_address');

        $page = $this->publishFormPage();
        $html = $this->get('/contato')->getContent();

        $this->post(route('site.contato.enviar'), [
            '_token' => $this->tokenFrom($html),
            'nome' => 'Sem destino',
            'email' => 'a@b.dev',
            'mensagem' => 'mensagem válida aqui',
        ])->assertRedirect();

        Mail::assertNothingSent();
    }

    public function test_the_fallback_recipient_is_the_from_address(): void
    {
        app(Settings::class)->forget('mail_to_address');

        $page = $this->publishFormPage();
        $html = $this->get('/contato')->getContent();

        $this->post(route('site.contato.enviar'), [
            '_token' => $this->tokenFrom($html),
            'nome' => 'Redirect',
            'email' => 'a@b.dev',
            'mensagem' => 'mensagem válida aqui',
        ]);

        Mail::assertSent(function (ContactReceived $mail): bool {
            return $mail->hasTo('site@meusite.dev');
        });
    }
}
