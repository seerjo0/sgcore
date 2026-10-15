<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\UpdateChecker;
use Illuminate\Console\Command;

class CheckUpdatesCommand extends Command
{
    protected $signature = 'sgcore:check-updates';

    protected $description = 'Consulta o último Release do GitHub e atualiza o aviso de atualização (somente leitura)';

    public function handle(UpdateChecker $updater): int
    {
        if (! config('cms.updater.enabled')) {
            $this->comment('Verificador de atualizações desativado.');

            return self::SUCCESS;
        }

        $update = $updater->updateAvailable(mayFetch: true);
        $current = $updater->currentVersion();

        if ($update !== null) {
            $this->components->warn("Nova versão disponível: {$update['tag']} (atual {$current}).");

            return self::SUCCESS;
        }

        if ($updater->latest() === null && ($error = $updater->lastError()) !== null) {
            $this->components->error("Não foi possível consultar o GitHub: {$error}");

            return self::FAILURE;
        }

        $this->info("sgcore {$current} está atualizado.");

        return self::SUCCESS;
    }
}
