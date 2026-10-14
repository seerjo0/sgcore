<?php

namespace App\Modules\Core\Services;

use Symfony\Component\Process\Process;

/**
 * Frees a TCP port used by the PHP built-in dev server before a new server is
 * started. The built-in `php -S` server is single-threaded: orphaned workers
 * left over from previous `artisan serve` runs (never killed) pile up and make
 * a new request hang on the busy port. This service detects the PIDs listening
 * on a port and, when asked to, terminates them.
 *
 * Every interaction with the OS goes through the injected $runner, so the class
 * is unit-testable without actually spawning/killing processes.
 */
class DevServerManager
{
    /**
     * @param  (\Closure(list<string>): string)|null  $runner  executes a command and returns trimmed stdout
     */
    public function __construct(private ?\Closure $runner = null) {}

    /**
     * PIDs currently listening on the given port (empty when free).
     *
     * @return list<int>
     */
    public function portListeners(int $port): array
    {
        $output = PHP_OS_FAMILY === 'Windows'
            ? $this->run([
                'powershell', '-NoProfile', '-Command',
                'Get-NetTCPConnection -LocalPort '.$port.' -State Listen -ErrorAction SilentlyContinue | ForEach-Object { $_.OwningProcess }',
            ])
            : $this->run(['sh', '-c', "lsof -ti tcp:{$port} -sTCP:LISTEN 2>/dev/null || true"]);

        $pids = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '' && ctype_digit($line) && ! in_array((int) $line, $pids, true)) {
                $pids[] = (int) $line;
            }
        }

        return $pids;
    }

    /**
     * Whether the PID belongs to a PHP process (guard so we never kill an
     * unrelated service that happens to hold the port).
     */
    public function isPhpProcess(int $pid): bool
    {
        $output = PHP_OS_FAMILY === 'Windows'
            ? $this->run(['powershell', '-NoProfile', '-Command', '(Get-Process -Id '.$pid.' -ErrorAction SilentlyContinue).ProcessName'])
            : $this->run(['ps', '-p', (string) $pid, '-o', 'comm=']);

        return stripos(trim($output), 'php') !== false;
    }

    public function kill(int $pid): bool
    {
        $output = PHP_OS_FAMILY === 'Windows'
            ? $this->run(['taskkill', '/F', '/PID', (string) $pid])
            : $this->run(['kill', '-9', (string) $pid]);

        // taskkill writes on success too; treat any "success"/"terminated" or
        // empty posix output (no error) as a kill.
        return PHP_OS_FAMILY === 'Windows'
            ? stripos($output, 'success') !== false || stripos($output, 'terminado') !== false || stripos($output, 'sucesso') !== false
            : trim($output) === '' || stripos($output, 'success') !== false;
    }

    /**
     * Detects and, when allowed, clears a busy port.
     *
     * @return array{found: list<int>, killed: list<int>, busy: bool}
     */
    public function freePort(int $port, bool $force): array
    {
        $found = $this->portListeners($port);
        $killed = [];

        if ($force) {
            foreach ($found as $pid) {
                if ($this->isPhpProcess($pid) && $this->kill($pid)) {
                    $killed[] = $pid;
                }
            }
        }

        $busy = $this->portListeners($port) !== [];

        return ['found' => $found, 'killed' => $killed, 'busy' => $busy];
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): string
    {
        if ($this->runner !== null) {
            return (string) ($this->runner)($command);
        }

        // Discovery commands legitimately exit non-zero (e.g. "no listeners on
        // the port", "no such PID"), so we read stdout regardless of status.
        $process = new Process($command, timeout: 10);
        $process->run();

        return $process->getOutput();
    }
}
