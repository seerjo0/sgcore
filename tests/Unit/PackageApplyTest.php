<?php

namespace Tests\Unit;

use App\Modules\Core\Services\PackageApply;
use App\Modules\Core\Services\PackageUpdater;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PackageApplyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();

        parent::tearDown();
    }

    public function test_applies_the_package_and_always_leaves_maintenance_mode(): void
    {
        $updater = Mockery::mock(PackageUpdater::class);
        $updater->shouldReceive('apply')->once()->andReturn([
            'from' => '1.1.0', 'to' => '1.2.0', 'copied' => 1, 'skipped' => 0, 'migrate' => 0, 'clear' => 1,
        ]);

        $result = (new PackageApply($updater))->apply('/tmp/pkg.zip', '1.1.0');

        $this->assertSame('1.2.0', $result['to']);
        $this->assertFileDoesNotExist($this->down());
        $this->assertFileDoesNotExist($this->lock());
    }

    public function test_leaves_maintenance_mode_when_the_update_throws(): void
    {
        $updater = Mockery::mock(PackageUpdater::class);
        $updater->shouldReceive('apply')->once()->andThrow(new RuntimeException('boom'));

        $apply = new PackageApply($updater);

        try {
            $apply->apply('/tmp/pkg.zip', '1.1.0');
            $this->fail('Expected the update to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertFileDoesNotExist($this->down());
        $this->assertFileDoesNotExist($this->lock());
    }

    public function test_refuses_to_start_while_another_update_is_running(): void
    {
        File::put($this->down(), '{}');
        File::put($this->lock(), (string) json_encode(['pid' => 1, 'version' => '1.2.0', 'started_at' => time()]));

        $updater = Mockery::mock(PackageUpdater::class);
        $updater->shouldNotReceive('apply');

        $this->expectException(RuntimeException::class);

        (new PackageApply($updater))->apply('/tmp/pkg.zip', '1.1.0');
    }

    public function test_heals_a_maintenance_file_left_behind_by_a_dead_process(): void
    {
        File::put($this->down(), '{}');
        touch($this->down(), time() - 3600);

        $apply = new PackageApply(Mockery::mock(PackageUpdater::class));

        $this->assertTrue($apply->status()['stale']);
        $this->assertTrue($apply->heal());
        $this->assertFileDoesNotExist($this->down());
    }

    public function test_heal_does_not_touch_a_live_update(): void
    {
        File::put($this->down(), '{}');
        File::put($this->lock(), (string) json_encode(['pid' => 1, 'version' => '1.2.0', 'started_at' => time()]));

        $apply = new PackageApply(Mockery::mock(PackageUpdater::class));

        $this->assertTrue($apply->status()['busy']);
        $this->assertFalse($apply->heal());
        $this->assertFileExists($this->down());
    }

    public function test_release_reports_whether_the_site_was_down(): void
    {
        $apply = new PackageApply(Mockery::mock(PackageUpdater::class));

        $this->assertFalse($apply->release());

        File::put($this->down(), '{}');

        $this->assertTrue($apply->release());
        $this->assertFileDoesNotExist($this->down());
    }

    private function clean(): void
    {
        File::delete([$this->down(), $this->lock(), storage_path('framework/maintenance.php')]);
    }

    private function down(): string
    {
        return storage_path('framework/down');
    }

    private function lock(): string
    {
        return storage_path('framework/update.lock');
    }
}
