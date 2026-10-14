<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\DevServerManager;
use Illuminate\Console\Command;

class ServeCommand extends Command
{
    protected $signature = 'sgcore:serve
        {--host=127.0.0.1 : Host do servidor}
        {--port=8000 : Porta do servidor}
        {--force : Encerra o servidor anterior que estiver segurando a porta}';

    protected $description = 'Sobe o php artisan serve encerrando antes workers dev órfãos na porta';

    public function handle(DevServerManager $manager): int
    {
        $port = (int) $this->option('port');
        $host = (string) $this->option('host');
        $force = (bool) $this->option('force');

        $result = $manager->freePort($port, $force);

        if ($result['killed'] !== []) {
            $this->components->info('Servidor(es) anterior(es) encerrado(s) na porta '.$port.': '.implode(', ', $result['killed']));
        }

        if ($result['busy'] && ! $force) {
            $this->error("A porta {$port} já está em uso (PID ".implode(', ', $result['found']).').');
            $this->line('Re rode com <fg=cyan>--force</> para encerrar o servidor anterior, ou use <fg=cyan>--port</> diferente.');

            return self::FAILURE;
        }

        if (app()->runningUnitTests()) {
            return self::SUCCESS;
        }

        return $this->call('serve', ['--host' => $host, '--port' => $port]);
    }
}
