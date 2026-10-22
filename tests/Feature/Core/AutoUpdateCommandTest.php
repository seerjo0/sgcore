<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Services\PackageApply;
use App\Modules\Core\Services\Settings;
use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdatePackageStore;
use App\Modules\Core\Services\UpdatePolicy;
use App\Modules\Core\Services\UpdateRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AutoUpdateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('app/updates'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/updates'));

        parent::tearDown();
    }

    public function test_does_nothing_when_the_panel_disables_auto_check(): void
    {
        app(UpdatePolicy::class)->save([
            'auto_check' => false,
            'auto_download' => true,
            'auto_install' => true,
        ]);

        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('Verificação automática desativada')
            ->assertExitCode(0);
    }

    public function test_no_op_when_already_on_latest(): void
    {
        $this->fakeChecker('1.0.1', 'v1.0.1');

        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('já é a versão mais recente')
            ->assertExitCode(0);
    }

    public function test_only_reports_a_new_version_when_install_is_disabled(): void
    {
        $this->fakeChecker('1.0.1', 'v9.9.9', asset: $this->asset());

        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('1.0.1 → v9.9.9 disponível')
            ->expectsOutputToContain('Instalação automática desativada')
            ->assertExitCode(0);

        $this->assertNull(app(UpdatePackageStore::class)->current());
    }

    public function test_downloads_the_package_when_auto_download_is_enabled(): void
    {
        $this->enable(['auto_check' => true, 'auto_download' => true, 'auto_install' => false]);
        $this->fakeChecker('1.0.1', 'v9.9.9', asset: $this->asset());

        Http::fake(['github.com/*' => Http::response('zip-bytes', 200)]);

        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('Pacote v9.9.9 baixado')
            ->expectsOutputToContain('Instalação automática desativada')
            ->assertExitCode(0);

        $stored = app(UpdatePackageStore::class)->current();

        $this->assertNotNull($stored);
        $this->assertSame('9.9.9', $stored['version']);
    }

    public function test_downloads_and_installs_the_package_when_enabled(): void
    {
        $this->enable(['auto_check' => true, 'auto_download' => true, 'auto_install' => true]);
        $this->fakeChecker('1.0.1', 'v9.9.9', asset: $this->asset());

        Http::fake(['github.com/*' => Http::response('zip-bytes', 200)]);

        $apply = Mockery::mock(PackageApply::class);
        $apply->shouldReceive('apply')
            ->once()
            ->withArgs(fn (string $path, string $from): bool => is_file($path)
                && str_contains($path, 'sgcore-9.9.9.zip')
                && $from === '1.0.1');
        $this->app->instance(PackageApply::class, $apply);

        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('Pacote v9.9.9 baixado')
            ->expectsOutputToContain('Atualização automática aplicada: 1.0.1 → v9.9.9')
            ->assertExitCode(0);

        $this->assertNull(app(UpdatePackageStore::class)->current());
        $this->assertSame('updated', app(Settings::class)->get('auto_update_last')['status']);
    }

    public function test_installs_a_package_downloaded_earlier(): void
    {
        $this->enable(['auto_check' => true, 'auto_download' => true, 'auto_install' => true]);
        $this->fakeChecker('1.0.1', 'v9.9.9', asset: $this->asset());

        $this->storePackage('9.9.9');

        $apply = Mockery::mock(PackageApply::class);
        $apply->shouldReceive('apply')->once();
        $this->app->instance(PackageApply::class, $apply);

        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('Atualização automática aplicada')
            ->assertExitCode(0);

        $this->assertNull(app(UpdatePackageStore::class)->current());
    }

    public function test_dry_run_reports_the_plan_without_downloading_or_applying(): void
    {
        $this->enable(['auto_check' => true, 'auto_download' => true, 'auto_install' => true]);
        $this->fakeChecker('1.0.1', 'v9.9.9', asset: $this->asset());

        $apply = Mockery::mock(PackageApply::class);
        $apply->shouldNotReceive('apply');
        $this->app->instance(PackageApply::class, $apply);

        $this->artisan('sgcore:auto-update', ['--dry-run' => true])
            ->expectsOutputToContain('Baixar automaticamente: sim')
            ->expectsOutputToContain('Instalar automaticamente: sim')
            ->assertExitCode(0);

        $this->assertNull(app(UpdatePackageStore::class)->current());
    }

    /**
     * @param  array{auto_check:bool,auto_download:bool,auto_install:bool}  $values
     */
    private function enable(array $values): void
    {
        config(['cms.updater.apply.enabled' => true, 'cms.updater.apply.package' => true]);

        app(UpdatePolicy::class)->save($values);

        $runner = Mockery::mock(UpdateRunner::class);
        $runner->shouldReceive('isEnabled')->andReturn(false);
        $this->app->instance(UpdateRunner::class, $runner);
    }

    private function fakeChecker(string $current, string $tag, ?array $asset = null): void
    {
        $checker = Mockery::mock(UpdateChecker::class);
        $checker->shouldReceive('latest')->andReturn([
            'tag' => $tag,
            'version' => ltrim($tag, 'v'),
            'name' => $tag,
            'url' => '',
            'published_at' => '',
            'body' => '',
            'assets' => [],
        ]);
        $checker->shouldReceive('currentVersion')->andReturn($current);
        $checker->shouldReceive('packageAsset')->andReturn($asset);

        $this->app->instance(UpdateChecker::class, $checker);
    }

    /**
     * @return array{name:string,url:string,size:int,digest:string}
     */
    private function asset(): array
    {
        return [
            'name' => 'sgcore-9.9.9-public_html.zip',
            'url' => 'https://github.com/seerjo0/sgcore/releases/download/v9.9.9/sgcore-9.9.9-public_html.zip',
            'size' => 2048,
            'digest' => '',
        ];
    }

    private function storePackage(string $version): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'sgcore-test-').'.zip';
        file_put_contents($temp, 'zip-bytes');

        app(UpdatePackageStore::class)->store($temp, $version);
    }
}
