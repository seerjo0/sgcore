<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdateRunner;
use App\Modules\Core\Support\Version;
use Illuminate\Console\Command;
use RuntimeException;

class UpdateCommand extends Command
{
    protected $signature = 'sgcore:update
        {--to= : Tag de destino (ex.: v1.1.0); padrão: a última release/tag}
        {--dry-run : Mostra o plano sem aplicar nada}
        {--force : Pula a confirmação interativa}
        {--no-backup : Não tenta backup do banco antes de aplicar}';

    protected $description = 'Aplica uma atualização a partir dos Releases/tags do GitHub (manutenção + backup + rollback)';

    public function handle(UpdateChecker $checker, UpdateRunner $runner): int
    {
        if (! $runner->isEnabled() && ! $this->option('dry-run')) {
            $this->error('O auto-apply está desativado. Defina CMS_UPDATER_ALLOW_APPLY=true para habilitar.');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! $this->confirmEnvironment($runner)) {
            return self::FAILURE;
        }

        $target = $this->resolveTargetTag($checker, $runner);

        if ($target === null) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->renderPlan($target, $runner, $checker);

            return self::SUCCESS;
        }

        return $this->doApply($target, $runner);
    }

    /**
     * @return array{tag:string,version:string}|null
     */
    private function resolveTarget(?UpdateChecker $checker): ?array
    {
        $explicit = trim((string) $this->option('to'));

        if ($explicit !== '') {
            return ['tag' => $explicit, 'version' => UpdateChecker::normalizeVersion($explicit)];
        }

        return $checker?->latest();
    }

    private function resolveTargetTag(?UpdateChecker $checker, UpdateRunner $runner): ?string
    {
        $resolved = $this->resolveTarget($checker);

        if ($resolved === null) {
            $this->error('Nenhuma versão de destino (use --to=vX.Y.Z ou configure cms.updater).');

            return null;
        }

        $tag = $resolved['tag'];
        $version = $resolved['version'];

        $current = $checker->currentVersion();

        if (! Version::isNewer($version, $current)) {
            $this->warn("Nada a fazer: destino {$tag} ({$version}) não é mais novo que a versão atual ({$current}).");

            if (! $this->option('dry-run') && ! $this->option('force')) {
                return null;
            }
        }

        return $tag;
    }

    private function renderPlan(string $target, UpdateRunner $runner, UpdateChecker $checker): void
    {
        $this->components->info('Plano de atualização ('.$checker->currentVersion().' → '.$target.')');

        $blockers = $runner->blockers();

        if ($blockers !== []) {
            $this->components->warn('Bloqueios: '.implode(' · ', $blockers));
        }

        $commits = $runner->incomingCommits($target);

        if ($commits !== '') {
            $this->line('');
            $this->components->twoColumnDetail('<fg=green>Commits de entrada</>');
            $this->line($commits);
        }

        $this->line('');
        $this->components->twoColumnDetail('<fg=green>Passos</>');

        foreach ($runner->plan($target) as $step) {
            $this->components->twoColumnDetail('  · '.$step['label']);
        }

        $this->line('');
        $this->comment('Rode sem --dry-run (e com CMS_UPDATER_ALLOW_APPLY=true) para aplicar.');
    }

    private function confirmEnvironment(UpdateRunner $runner): bool
    {
        $blockers = $runner->blockers();

        if ($blockers !== []) {
            $this->error('Não é seguro aplicar: '.implode(' · ', $blockers));

            return false;
        }

        if ($this->option('force')) {
            return true;
        }

        return $this->confirm(
            'Isto vai trocar código (git), rodar composer install e migrate. Continuar?',
            false
        );
    }

    private function doApply(string $target, UpdateRunner $runner): int
    {
        @set_time_limit(0);
        $this->components->info('Aplicando '.$target.'…');

        try {
            $runner->apply($target, withBackup: ! $this->option('no-backup'));
        } catch (RuntimeException $e) {
            $this->components->error('Falha: '.$e->getMessage());
            $this->line('Rollback foi tentado automaticamente.');

            return self::FAILURE;
        }

        $this->components->info('Atualização aplicada. Não esqueça de conferir o changelog e atualizar CMS_VERSION no .env.');

        return self::SUCCESS;
    }
}
