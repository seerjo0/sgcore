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

        if ($update === null) {
            $this->info('sgcore '.$updater->currentVersion().' está atualizado.');

            return self::SUCCESS;
        }

        $this->components->warn("Nova versão disponível: {$update['tag']} (atual {$updater->currentVersion()}).");

        return self::SUCCESS;
    }
}
