<?php

namespace Tests\Feature;

use App\Modules\Core\Services\PackageSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PackageSourceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temps = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cms.updater.repository' => 'seerjo0/sgcore',
            'cms.updater.apply.max_package_bytes' => 200 * 1024 * 1024,
            'cms.updater.apply.download_timeout' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->temps as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_download_ok_returns_temp_path_and_bytes(): void
    {
        $body = str_repeat('A', 1024);
        $digest = hash('sha256', $body);

        Http::fake([
            'objects.githubusercontent.com/*' => Http::response($body, 200),
        ]);

        $result = (new PackageSource)->fetch([
            'name' => 'sgcore.zip',
            'url' => 'https://objects.githubusercontent.com/seerjo0/sgcore/releases/download/v1.2.0/sgcore.zip',
            'size' => strlen($body),
            'digest' => $digest,
        ]);

        $this->temps[] = $result['path'];

        $this->assertGreaterThan(0, $result['bytes']);
        $this->assertFileExists($result['path']);
        $this->assertSame($body, file_get_contents($result['path']));
    }

    public function test_rejects_host_outside_github(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);

        (new PackageSource)->fetch([
            'name' => 'sgcore.zip',
            'url' => 'https://evil.example.com/seerjo0/sgcore/sgcore.zip',
            'size' => 10,
            'digest' => '',
        ]);
    }

    public function test_rejects_url_from_other_repository(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);

        (new PackageSource)->fetch([
            'name' => 'other.zip',
            'url' => 'https://github.com/attacker/other/releases/download/v1/other.zip',
            'size' => 10,
            'digest' => '',
        ]);
    }

    public function test_rejects_oversized_package(): void
    {
        config(['cms.updater.apply.max_package_bytes' => 10]);

        Http::fake();

        $this->expectException(RuntimeException::class);

        (new PackageSource)->fetch([
            'name' => 'huge.zip',
            'url' => 'https://github.com/seerjo0/sgcore/releases/download/v1/huge.zip',
            'size' => 10_000,
            'digest' => '',
        ]);
    }

    public function test_rejects_digest_mismatch(): void
    {
        $body = 'package-bytes';

        Http::fake([
            'github.com/*' => Http::response($body, 200),
        ]);

        $this->expectException(RuntimeException::class);

        (new PackageSource)->fetch([
            'name' => 'sgcore.zip',
            'url' => 'https://github.com/seerjo0/sgcore/releases/download/v1/sgcore.zip',
            'size' => strlen($body),
            'digest' => hash('sha256', 'different'),
        ]);
    }

    public function test_wraps_download_error(): void
    {
        Http::fake([
            'github.com/*' => Http::response('nope', 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Falha ao baixar/');

        (new PackageSource)->fetch([
            'name' => 'sgcore.zip',
            'url' => 'https://github.com/seerjo0/sgcore/releases/download/v1/sgcore.zip',
            'size' => 4,
            'digest' => '',
        ]);
    }
}
