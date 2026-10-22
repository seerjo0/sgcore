<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\Settings;
use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdateRunner;
use App\Modules\Core\Support\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Opt-in scheduled auto-update. It only pulls from the repository and applies a
 * newer release when the operator explicitly enables it, and it always delegates
 * the actual swap to sgcore:update --force so the existing safety chain runs
 * (maintenance mode, DB backup, composer install, migrate, optimize:clear and
 * automatic rollback on failure).
 */
class AutoUpdateCommand extends Command
{
    protected $signature = 'sgcore:auto-update
        {--dry-run : Mostra o que seria feito, sem aplicar}
        {--no-backup : Aplica sem backup do banco (repassa ao sgcore:update)}';

    protected $description = 'Checa o GitHub e aplica atualizações automaticamente (exige CMS_UPDATER_AUTO + CMS_UPDATER_ALLOW_APPLY)';

    public function handle(UpdateChecker $checker, UpdateRunner $runner): int
    {
        if (! config('cms.updater.auto.enabled')) {
            $this->line('Auto-update desativado. Defina CMS_UPDATER_AUTO=true para habilitar.');

            return self::SUCCESS;
        }

        if (! $runner->isEnabled()) {
            $this->line('Auto-update pulado: o auto-apply está desativado (defina CMS_UPDATER_ALLOW_APPLY=true).');

            return self::SUCCESS;
        }

        $latest = $checker->latest();

        if ($latest === null) {
            $this->line('Auto-update: sem informação de versão do GitHub no momento; nada a fazer.');

            return self::SUCCESS;
        }

        $current = $checker->currentVersion();

        if (! Version::isNewer($latest['version'], $current)) {
            $this->line("Auto-update: {$current} já é a versão mais recente.");

            return self::SUCCESS;
        }

        $this->info("Auto-update: {$current} → {$latest['tag']} disponível.");

        if ($this->option('dry-run')) {
            $this->line("Passo a passo: seria aplicado {$latest['tag']} via `sgcore:update --to={$latest['tag']} --force`.");

            return self::SUCCESS;
        }

        if (! $runner->isGitCheckout()) {
            $this->error('Auto-update requer uma instalação via Git (sem .git) — não é suportado em instalações por .zip.');

            return self::FAILURE;
        }

        if (($blockers = $runner->blockers()) !== []) {
            $this->error('Auto-update abortado por bloqueios: '.implode(' · ', $blockers));
            $this->record($current, $latest['tag'], 'blocked');
            Log::warning('sgcore:auto-update blocked', ['blockers' => $blockers]);

            return self::FAILURE;
        }

        return $this->apply($current, $latest['tag'], $runner);
    }

    private function apply(string $from, string $to, UpdateRunner $runner): int
    {
        $this->record($from, $to, 'attempting');

        $status = $this->call('sgcore:update', array_filter([
            '--to' => $to,
            '--force' => true,
            '--no-backup' => $this->option('no-backup') ?: null,
        ]));

        $ok = $status === self::SUCCESS;

        $this->record($from, $to, $ok ? 'updated' : 'failed');
        Log::info('sgcore:auto-update', ['from' => $from, 'to' => $to, 'ok' => $ok]);

        if ($ok) {
            $this->components->info("Atualização automática aplicada: {$from} → {$to}.");
        } else {
            $this->components->error("A atualização automática para {$to} falhou (rollback foi tentado).");
        }

        return $status;
    }

    private function record(string $from, string $to, string $status): void
    {
        try {
            app(Settings::class)->set('auto_update_last', [
                'from' => $from,
                'to' => $to,
                'status' => $status,
                'at' => now()->toIso8601String(),
            ], 'general');
        } catch (\Throwable) {
            // Recording is best-effort; never block the update on a settings write.
        }
    }
}
