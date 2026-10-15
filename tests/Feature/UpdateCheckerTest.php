<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Core\Services\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeInstalled();
        config(['cms.version' => '1.0.0', 'cms.updater.repository' => 'seerjo0/sgcore', 'cms.updater.enabled' => true]);
    }

    private function fakeRelease(array $overrides = []): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(array_merge([
                'tag_name' => 'v1.2.0',
                'name' => 'Version 1.2.0',
                'html_url' => 'https://github.com/seerjo0/sgcore/releases/tag/v1.2.0',
                'published_at' => '2026-10-13T00:00:00Z',
                'body' => 'changelog',
            ], $overrides), 200),
        ]);
    }

    public function test_reports_a_newer_release(): void
    {
        $this->fakeRelease();

        $update = app(UpdateChecker::class)->updateAvailable();

        $this->assertNotNull($update);
        $this->assertSame('v1.2.0', $update['tag']);
        $this->assertSame('1.2.0', $update['version']);
        $this->assertStringContainsString('github.com/seerjo0/sgcore/releases', $update['url']);
    }

    public function test_no_update_when_versions_are_equal(): void
    {
        $this->fakeRelease(['tag_name' => 'v1.0.0']);

        $this->assertNull(app(UpdateChecker::class)->updateAvailable());
    }

    public function test_never_offers_a_downgrade(): void
    {
        $this->fakeRelease(['tag_name' => 'v0.9.0']);

        $this->assertNull(app(UpdateChecker::class)->updateAvailable());
    }

    public function test_compares_multi_digit_versions_numerically(): void
    {
        config(['cms.version' => '1.9.0']);
        $this->fakeRelease(['tag_name' => 'v1.10.0']);

        $update = app(UpdateChecker::class)->updateAvailable();

        $this->assertNotNull($update);
        $this->assertSame('1.10.0', $update['version']);
    }

    public function test_fails_soft_when_github_is_unreachable(): void
    {
        Http::fake(['api.github.com/*' => Http::response(null, 500)]);

        $this->assertNull(app(UpdateChecker::class)->updateAvailable());
    }

    public function test_caches_the_result_and_hits_github_once(): void
    {
        $this->fakeRelease();
        $checker = app(UpdateChecker::class);

        $checker->updateAvailable();
        $checker->latest();

        Http::assertSentCount(1);
    }

    public function test_respects_disabled_flag_without_calling_github(): void
    {
        Http::fake();
        config(['cms.updater.enabled' => false]);

        $this->assertNull(app(UpdateChecker::class)->updateAvailable());

        Http::assertNothingSent();
    }

    public function test_rejects_a_non_github_release_url(): void
    {
        $this->fakeRelease(['html_url' => 'javascript:alert(1)']);

        $update = app(UpdateChecker::class)->updateAvailable();

        $this->assertNotNull($update);
        $this->assertSame('', $update['url']);
    }

    public function test_rejects_a_release_url_from_another_repository(): void
    {
        $this->fakeRelease(['html_url' => 'https://github.com/attacker/sgcore/releases/tag/v1.2.0']);

        $this->assertSame('', app(UpdateChecker::class)->updateAvailable()['url']);
    }

    public function test_admin_sees_update_banner_on_dashboard(): void
    {
        $this->fakeRelease();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('updater.banner_title'));
    }

    public function test_editor_does_not_see_update_banner(): void
    {
        $this->fakeRelease();
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(__('updater.banner_title'));
    }

    public function test_check_now_endpoint_flashes_result_for_admin(): void
    {
        $this->fakeRelease();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.configuracoes.atualizacoes.verificar'))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_only_admin_can_use_the_update_routes(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)
            ->post(route('admin.configuracoes.atualizacoes.verificar'))
            ->assertForbidden();
    }

    public function test_dismiss_hides_the_banner_for_that_version(): void
    {
        $this->fakeRelease();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.configuracoes.atualizacoes.descartar'), ['tag' => 'v1.2.0'])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(__('updater.banner_title'));
    }
}
