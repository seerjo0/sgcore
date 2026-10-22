<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Modules\Core\Services\PackageApply;
use App\Modules\Core\Services\Settings;
use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdatePackageStore;
use App\Modules\Core\Services\UpdatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class UpdatesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();

        File::deleteDirectory(storage_path('app/updates'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/updates'));

        parent::tearDown();
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

    public function test_downloads_the_package_without_installing_it(): void
    {
        config(['cms.version' => '1.1.0-r1', 'cms.updater.apply.enabled' => true]);
        $this->fakeRelease('v1.1.0-r2', withPackage: true);

        $apply = Mockery::mock(PackageApply::class);
        $apply->shouldNotReceive('apply');
        $this->app->instance(PackageApply::class, $apply);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.configuracoes.atualizacoes.baixar'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $stored = app(UpdatePackageStore::class)->current();

        $this->assertNotNull($stored);
        $this->assertSame('1.1.0-r2', $stored['version']);
        $this->assertFileExists($stored['path']);
    }

    public function test_the_page_lists_the_downloaded_package(): void
    {
        config(['cms.version' => '1.1.0-r1', 'cms.updater.apply.enabled' => true]);
        $this->fakeRelease('v1.1.0-r2', withPackage: true);
        $this->storePackage('1.1.0-r2');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.configuracoes.atualizacoes.index'))
            ->assertOk()
            ->assertSee(__('updater.package_ready'), false)
            ->assertSee(__('updater.package_install', ['version' => '1.1.0-r2']), false)
            ->assertSee(__('updater.package_discard'), false);
    }

    public function test_installs_the_downloaded_package_and_discards_it(): void
    {
        config(['cms.version' => '1.1.0-r1', 'cms.updater.apply.enabled' => true]);
        $this->storePackage('1.1.0-r2');

        $apply = Mockery::mock(PackageApply::class);
        $apply->shouldReceive('apply')
            ->once()
            ->withArgs(fn (string $path, string $from): bool => is_file($path) && $from === '1.1.0-r1');
        $this->app->instance(PackageApply::class, $apply);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.configuracoes.atualizacoes.instalar'), ['confirm' => '1'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        $this->assertNull(app(UpdatePackageStore::class)->current());
    }

    public function test_install_requires_the_confirmation_checkbox(): void
    {
        config(['cms.version' => '1.1.0-r1', 'cms.updater.apply.enabled' => true]);
        $this->storePackage('1.1.0-r2');

        $apply = Mockery::mock(PackageApply::class);
        $apply->shouldNotReceive('apply');
        $this->app->instance(PackageApply::class, $apply);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.configuracoes.atualizacoes.instalar'))
            ->assertSessionHasErrors('confirm');

        $this->assertNotNull(app(UpdatePackageStore::class)->current());
    }

    public function test_install_refuses_a_package_that_is_not_newer(): void
    {
        config(['cms.version' => '1.1.0-r2', 'cms.updater.apply.enabled' => true]);
        $this->storePackage('1.1.0-r2');

        $apply = Mockery::mock(PackageApply::class);
        $apply->shouldNotReceive('apply');
        $this->app->instance(PackageApply::class, $apply);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.configuracoes.atualizacoes.instalar'), ['confirm' => '1'])
            ->assertSessionHas('error', __('updater.package_not_newer', ['version' => '1.1.0-r2']));
    }

    public function test_discards_the_downloaded_package(): void
    {
        $this->storePackage('1.1.0-r2');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.configuracoes.atualizacoes.pacote.descartar'))
            ->assertRedirect()
            ->assertSessionHas('status', __('updater.package_discarded'));

        $this->assertNull(app(UpdatePackageStore::class)->current());
    }

    public function test_saves_the_automatic_update_preferences(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.atualizacoes.config'), [
                'auto_check' => '1',
                'auto_download' => '1',
                'auto_install' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', __('updater.settings_saved'));

        $policy = new UpdatePolicy(new Settings);

        $this->assertTrue($policy->autoCheck());
        $this->assertTrue($policy->autoDownload());
        $this->assertTrue($policy->autoInstall());

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.atualizacoes.config'))
            ->assertRedirect();

        $policy = new UpdatePolicy(new Settings);

        $this->assertFalse($policy->autoCheck());
        $this->assertFalse($policy->autoDownload());
        $this->assertFalse($policy->autoInstall());
    }

    public function test_enabling_install_also_enables_download(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.configuracoes.atualizacoes.config'), [
                'auto_check' => '1',
                'auto_install' => '1',
            ])
            ->assertRedirect();

        $policy = new UpdatePolicy(new Settings);

        $this->assertTrue($policy->autoDownload());
        $this->assertTrue($policy->autoInstall());
    }

    private function storePackage(string $version): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'sgcore-test-').'.zip';
        file_put_contents($temp, 'zip-bytes');

        app(UpdatePackageStore::class)->store($temp, $version);
    }

    private function fakeRelease(string $tag, bool $withPackage = false): void
    {
        $version = ltrim($tag, 'v');

        $assets = $withPackage ? [[
            'name' => "sgcore-{$version}-public_html.zip",
            'browser_download_url' => "https://github.com/seerjo0/sgcore/releases/download/{$tag}/sgcore-{$version}-public_html.zip",
            'size' => 2048,
            'digest' => '',
        ]] : [];

        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => $tag,
                'name' => "sgcore {$tag}",
                'html_url' => "https://github.com/seerjo0/sgcore/releases/tag/{$tag}",
                'published_at' => '2026-10-22T00:00:00Z',
                'body' => '',
                'assets' => $assets,
            ], 200),
            'github.com/*' => Http::response('zip-bytes', 200),
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
