<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdateRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AutoUpdateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_by_default(): void
    {
        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('Auto-update desativado')
            ->assertExitCode(0);
    }

    public function test_skipped_when_auto_apply_is_disabled(): void
    {
        config(['cms.updater.auto.enabled' => true]);
        config(['cms.updater.apply.enabled' => false]);

        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('pulado')
            ->assertExitCode(0);
    }

    public function test_no_op_when_already_on_latest(): void
    {
        config(['cms.updater.auto.enabled' => true]);

        $checker = Mockery::mock(UpdateChecker::class);
        $checker->shouldReceive('latest')->andReturn(['tag' => 'v1.0.1', 'version' => '1.0.1']);
        $checker->shouldReceive('currentVersion')->andReturn('1.0.1');

        $runner = Mockery::mock(UpdateRunner::class);
        $runner->shouldReceive('isEnabled')->andReturn(true);

        $this->app->instance(UpdateChecker::class, $checker);
        $this->app->instance(UpdateRunner::class, $runner);

        $this->artisan('sgcore:auto-update')
            ->expectsOutputToContain('já é a versão mais recente')
            ->assertExitCode(0);
    }

    public function test_dry_run_reports_a_planned_update_without_applying(): void
    {
        config(['cms.updater.auto.enabled' => true]);

        $checker = Mockery::mock(UpdateChecker::class);
        $checker->shouldReceive('latest')->andReturn(['tag' => 'v9.9.9', 'version' => '9.9.9']);
        $checker->shouldReceive('currentVersion')->andReturn('1.0.1');

        $runner = Mockery::mock(UpdateRunner::class);
        $runner->shouldReceive('isEnabled')->andReturn(true);

        $this->app->instance(UpdateChecker::class, $checker);
        $this->app->instance(UpdateRunner::class, $runner);

        // The command must never invoke sgcore:update in dry-run mode.
        $runner->shouldNotReceive('apply');

        $this->artisan('sgcore:auto-update', ['--dry-run' => true])
            ->expectsOutputToContain('seria aplicado v9.9.9')
            ->assertExitCode(0);
    }
}
