<?php

namespace Tests\Unit;

use App\Modules\Core\Services\DevServerManager;
use PHPUnit\Framework\TestCase;

class DevServerManagerTest extends TestCase
{
    /**
     * A runner that returns canned stdout per command, so the assertions are the
     * same whether the host is Windows or *nix (freePort uses the OS branch but
     * the fake answers both shapes).
     *
     * @param  array<int, string>  $listeners
     */
    private function manager(array $listeners, string $procName = 'php.exe', string $killReply = 'SUCCESS'): DevServerManager
    {
        $pids = implode("\n", array_map('str', $listeners));

        return new DevServerManager(function (array $cmd) use ($pids, $procName, $killReply): string {
            $head = strtolower($cmd[0] ?? '');

            if ($head === 'powershell' || $head === 'sh') {
                // listener probe vs process-name probe are distinguished by the tail args
                return str_contains(implode(' ', $cmd), 'OwningProcess') || str_contains(implode(' ', $cmd), 'LISTEN')
                    ? $pids
                    : $procName;
            }

            if ($head === 'lsof') {
                return $pids;
            }

            if ($head === 'ps') {
                return $procName;
            }

            if ($head === 'taskkill' || $head === 'kill') {
                return $killReply;
            }

            return '';
        });
    }

    public function test_parses_listening_pids_and_dedups(): void
    {
        $manager = $this->manager(['1234', '5678', '1234']);

        $this->assertSame([1234, 5678], $manager->portListeners(8000));
    }

    public function test_no_listeners_means_free_port(): void
    {
        $manager = $this->manager([]);

        $this->assertSame([], $manager->portListeners(8000));

        $result = $manager->freePort(8000, true);
        $this->assertFalse($result['busy']);
        $this->assertSame([], $result['killed']);
    }

    public function test_without_force_reports_busy_but_kills_nothing(): void
    {
        $manager = $this->manager(['4321']);

        $result = $manager->freePort(8000, false);

        $this->assertSame([4321], $result['found']);
        $this->assertTrue($result['busy']);
        $this->assertSame([], $result['killed']);
    }

    public function test_with_force_kills_php_listeners(): void
    {
        $manager = $this->manager(['4321', '9876'], procName: 'php', killReply: 'SUCCESS: PID terminated');

        $result = $manager->freePort(8000, true);

        $this->assertSame([4321, 9876], $result['killed']);
    }

    public function test_never_kills_a_non_php_process_on_the_port(): void
    {
        $manager = $this->manager(['4321'], procName: 'node');

        $result = $manager->freePort(8000, true);

        $this->assertSame([], $result['killed']);
    }

    public function test_is_php_process_detects_by_name(): void
    {
        $this->assertTrue($this->manager(['1'], procName: 'php.exe')->isPhpProcess(1));
        $this->assertTrue($this->manager(['1'], procName: 'php8.5')->isPhpProcess(1));
        $this->assertFalse($this->manager(['1'], procName: 'nginx')->isPhpProcess(1));
    }
}
