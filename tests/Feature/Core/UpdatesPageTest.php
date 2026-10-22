<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Services\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdatesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
    }

    public function test_admin_can_open_the_updates_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertOk()
            ->assertSee(__('updater.page_title'), false)
            ->assertSee(__('updater.manual'), false);
    }

    public function test_editor_cannot_open_the_updates_page(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_the_updates_page_highlights_only_the_updates_link(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertOk()
            ->getContent();

        $this->assertSidebarLinkActive($html, route('admin.configuracoes.atualizacoes.index'), true);
        $this->assertSidebarLinkActive($html, route('admin.configuracoes.index'), false);
    }

    public function test_the_settings_page_highlights_only_the_settings_link(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)
            ->get(route('admin.configuracoes.index'))
            ->assertOk()
            ->getContent();

        $this->assertSidebarLinkActive($html, route('admin.configuracoes.index'), true);
        $this->assertSidebarLinkActive($html, route('admin.configuracoes.atualizacoes.index'), false);
    }

    public function test_shows_the_available_version_in_a_green_callout(): void
    {
        config(['cms.version' => '1.1.0']);
        $this->fakeRelease('v1.1.0-r2');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertOk()
            ->assertSee('bg-emerald-50', false)
            ->assertSee('v1.1.0-r2', false)
            ->assertSee(__('updater.available_version'), false)
            ->assertDontSee(':version', false)
            ->assertDontSee(__('updater.up_to_date'), false);
    }

    public function test_shows_a_neutral_message_when_up_to_date(): void
    {
        config(['cms.version' => '1.1.0-r1']);
        $this->fakeRelease('v1.1.0-r1');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertOk()
            ->assertSee(__('updater.up_to_date'), false)
            ->assertDontSee('bg-emerald-50', false)
            ->assertDontSee(':version', false);
    }

    private function fakeRelease(string $tag): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => $tag,
                'name' => "sgcore {$tag}",
                'html_url' => "https://github.com/seerjo0/sgcore/releases/tag/{$tag}",
                'published_at' => '2026-10-22T00:00:00Z',
                'body' => '',
            ], 200),
        ]);

        app(UpdateChecker::class)->latest(true);
    }

    private function assertSidebarLinkActive(string $html, string $href, bool $active): void
    {
        $classes = $this->sidebarLinkClasses($html, $href);

        $this->assertNotEmpty($classes, "Link {$href} ausente da barra lateral.");

        $highlighted = array_filter(
            $classes,
            static fn (string $class): bool => str_contains($class, 'bg-slate-700'),
        );

        $active
            ? $this->assertNotEmpty($highlighted, "Link {$href} deveria estar destacado.")
            : $this->assertSame([], array_values($highlighted), "Link {$href} não deveria estar destacado.");
    }

    /**
     * @return list<string>
     */
    private function sidebarLinkClasses(string $html, string $href): array
    {
        $dom = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $href = htmlspecialchars($href, ENT_QUOTES);
        $classes = [];

        foreach ($xpath->query('//a[@href="'.$href.'"]') as $node) {
            $classes[] = $node->getAttribute('class');
        }

        return $classes;
    }
}
