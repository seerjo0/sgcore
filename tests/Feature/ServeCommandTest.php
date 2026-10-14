<?php

namespace Tests\Feature;

use App\Modules\Core\Services\DevServerManager;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class ServeCommandTest extends TestCase
{
    private function fakeManager(array $result): void
    {
        $mock = Mockery::mock(DevServerManager::class);
        $mock->shouldReceive('freePort')->andReturn($result);

        $this->app->instance(DevServerManager::class, $mock);
    }

    public function test_reports_failure_when_port_is_busy_and_not_forced(): void
    {
        $this->fakeManager(['found' => [4321], 'killed' => [], 'busy' => true]);

        $exit = Artisan::call('sgcore:serve', ['--port' => 8199]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('em uso', Artisan::output());
    }

    public function test_succeeds_when_port_is_free(): void
    {
        $this->fakeManager(['found' => [], 'killed' => [], 'busy' => false]);

        $exit = Artisan::call('sgcore:serve', ['--port' => 8199]);

        // In unit tests the real server is not launched; the orchestration path returns success.
        $this->assertSame(0, $exit);
    }

    public function test_succeeds_when_orphans_were_freed_with_force(): void
    {
        $this->fakeManager(['found' => [4321, 5678], 'killed' => [4321, 5678], 'busy' => false]);

        $exit = Artisan::call('sgcore:serve', ['--port' => 8199, '--force' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('encerrado', Artisan::output());
    }
}
