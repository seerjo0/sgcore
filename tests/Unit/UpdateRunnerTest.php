<?php

namespace Tests\Unit;

use App\Modules\Core\Services\UpdateRunner;
use Tests\TestCase;

class UpdateRunnerTest extends TestCase
{
    /**
     * @param  array<string,array{code:int,out:string}>  $byKey  map of "match" pattern => [code,out]
     * @param  array<int,array{0:list<string>,1:array<string,string>}>  $captured
     */
    private function runner(array $byKey, ?array &$captured = null, bool $enabled = true): UpdateRunner
    {
        $captured = [];

        $runner = new UpdateRunner(function (array $argv, array $env) use ($byKey, &$captured): array {
            $captured[] = [$argv, $env];
            $joined = implode(' ', $argv);

            foreach ($byKey as $needle => $ret) {
                if (str_contains($joined, $needle)) {
                    return [$ret['code'], $ret['out']];
                }
            }

            return [0, ''];
        });

        if ($enabled) {
            config(['cms.updater.apply.enabled' => true]);
        }

        return $runner;
    }

    public function test_blockers_when_apply_disabled(): void
    {
        $runner = new UpdateRunner(fn () => [0, '']);

        config(['cms.updater.apply.enabled' => false]);

        $this->assertContains('auto-apply disabled', $runner->blockers());
    }

    public function test_blockers_when_not_a_git_checkout(): void
    {
        $captured = [];
        $runner = $this->runner(['rev-parse --is-inside-work-tree' => ['code' => 128, 'out' => '']], $captured);

        $this->assertContains('not a git checkout', $runner->blockers());
    }

    public function test_blockers_when_tree_is_dirty(): void
    {
        $captured = [];
        $runner = $this->runner([
            'rev-parse --is-inside-work-tree' => ['code' => 0, 'out' => 'true'],
            'status --porcelain' => ['code' => 0, 'out' => " M app/Modules/Custom.php\n"],
        ], $captured);

        $this->assertContains('uncommitted changes', $runner->blockers());
    }

    public function test_no_blockers_when_clean_git_checkout(): void
    {
        $captured = [];
        $runner = $this->runner([
            'rev-parse --is-inside-work-tree' => ['code' => 0, 'out' => 'true'],
            'status --porcelain' => ['code' => 0, 'out' => ''],
        ], $captured);

        $this->assertSame([], $runner->blockers());
    }

    public function test_plan_lists_the_expected_steps(): void
    {
        $captured = [];
        $runner = $this->runner(['rev-parse --is-inside-work-tree' => ['code' => 0, 'out' => 'true']], $captured);

        $keys = array_column($runner->plan('v1.2.0'), 'key');

        $this->assertSame(['down', 'backup', 'fetch', 'checkout', 'composer', 'migrate', 'caches', 'up'], $keys);
    }

    public function test_apply_refuses_when_disabled(): void
    {
        $captured = [];
        $runner = $this->runner(['rev-parse --is-inside-work-tree' => ['code' => 0, 'out' => 'true']], $captured, enabled: false);

        $this->expectException(\RuntimeException::class);
        $runner->apply('v1.2.0');
    }

    public function test_apply_runs_every_step_in_order_when_all_succeed(): void
    {
        $captured = [];
        $runner = $this->runner([
            'rev-parse --is-inside-work-tree' => ['code' => 0, 'out' => 'true'],
            'status --porcelain' => ['code' => 0, 'out' => ''],
            'rev-parse HEAD' => ['code' => 0, 'out' => "abc123\n"],
            'rev-parse --verify --quiet tags/v1.2.0' => ['code' => 0, 'out' => ''],
        ], $captured);

        $runner->apply('v1.2.0', withBackup: false);

        $joined = array_map(fn ($c) => implode(' ', $c[0]), $captured);
        $sequence = implode(' | ', $joined);

        $this->assertStringContainsString('down', $sequence);
        $this->assertStringContainsString('fetch', $sequence);
        $this->assertStringContainsString('checkout --detach tags/v1.2.0', $sequence);
        $this->assertStringContainsString('composer install', $sequence);
        $this->assertStringContainsString('artisan migrate --force', $sequence);
        $this->assertStringContainsString('optimize:clear', $sequence);
        $this->assertStringContainsString('up', $sequence);
    }

    public function test_apply_rolls_back_when_migrate_fails(): void
    {
        $captured = [];
        $runner = $this->runner([
            'rev-parse --is-inside-work-tree' => ['code' => 0, 'out' => 'true'],
            'status --porcelain' => ['code' => 0, 'out' => ''],
            'rev-parse HEAD' => ['code' => 0, 'out' => "abc123\n"],
            'rev-parse --verify --quiet tags/v1.2.0' => ['code' => 0, 'out' => ''],
            'migrate --force' => ['code' => 1, 'out' => 'boom'],
        ], $captured);

        try {
            $runner->apply('v1.2.0', withBackup: false);
            $this->fail('expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('migrate', $e->getMessage());
        }

        $joined = implode(' | ', array_map(fn ($c) => implode(' ', $c[0]), $captured));
        $this->assertStringContainsString('checkout --detach abc123', $joined);
        $this->assertStringContainsString('composer install', $joined);
    }

    public function test_apply_refuses_dirty_tree(): void
    {
        $captured = [];
        $runner = $this->runner([
            'rev-parse --is-inside-work-tree' => ['code' => 0, 'out' => 'true'],
            'status --porcelain' => ['code' => 0, 'out' => " M file.php\n"],
        ], $captured);

        $this->expectException(\RuntimeException::class);
        $runner->apply('v1.2.0');
    }
}
