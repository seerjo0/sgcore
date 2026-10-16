<?php

namespace Tests\Feature;

use App\Modules\Core\Services\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateCheckerPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cms.version' => '1.0.0', 'cms.updater.repository' => 'seerjo0/sgcore', 'cms.updater.enabled' => true]);
    }

    private function fakeReleaseWithAssets(array $assets): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => 'v1.2.0',
                'name' => 'Version 1.2.0',
                'html_url' => 'https://github.com/seerjo0/sgcore/releases/tag/v1.2.0',
                'published_at' => '2026-10-13T00:00:00Z',
                'body' => 'changelog',
                'assets' => $assets,
            ], 200),
        ]);
    }

    public function test_package_asset_is_picked_from_release(): void
    {
        $hex = str_repeat('ab', 32);

        $this->fakeReleaseWithAssets([
            // GitHub's auto-generated source archive: name does NOT end in .zip, so it is skipped.
            ['name' => 'Source code (zip)', 'browser_download_url' => 'https://github.com/seerjo0/sgcore/archive/refs/tags/v1.2.0.zip', 'size' => 10, 'digest' => 'sha256:'.$hex],
            // The hardened package sgcore:package uploads: this is the one we want.
            ['name' => 'sgcore-package.zip', 'browser_download_url' => 'https://objects.githubusercontent.com/seerjo0/sgcore/releases/download/v1.2.0/sgcore-package.zip', 'size' => 1234, 'digest' => 'sha256:'.$hex],
        ]);

        $update = app(UpdateChecker::class)->updateAvailable();
        $package = app(UpdateChecker::class)->packageAsset($update);

        $this->assertNotNull($package);
        $this->assertSame('sgcore-package.zip', $package['name']);
        $this->assertSame(1234, $package['size']);
        $this->assertSame($hex, $package['digest']);
        $this->assertStringContainsString('objects.githubusercontent.com', $package['url']);
    }

    public function test_returns_null_when_no_zip_asset(): void
    {
        $this->fakeReleaseWithAssets([
            ['name' => 'notes.txt', 'browser_download_url' => 'https://github.com/seerjo0/sgcore/releases/download/v1.2.0/notes.txt', 'size' => 5, 'digest' => ''],
        ]);

        $update = app(UpdateChecker::class)->updateAvailable();

        $this->assertNull(app(UpdateChecker::class)->packageAsset($update));
    }

    public function test_returns_null_for_null_release(): void
    {
        $this->assertNull(app(UpdateChecker::class)->packageAsset(null));
    }
}
