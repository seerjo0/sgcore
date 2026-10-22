<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\PackageApply;
use App\Modules\Core\Services\PackageSource;
use App\Modules\Core\Services\Settings;
use App\Modules\Core\Services\UpdateChecker;
use App\Modules\Core\Services\UpdatePackageStore;
use App\Modules\Core\Services\UpdatePolicy;
use App\Modules\Core\Services\UpdateRunner;
use App\Modules\Core\Support\Version;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Scheduled update job driven by the panel preferences (check / download /
 * install). It checks GitHub, stores the release .zip when asked to, and only
 * swaps files when the operator enabled auto-install — in which case the
 * package path is preferred (works on shared hosting), with the Git runner as a
 * fallback for clones that have no package asset.
 */
class AutoUpdateCommand extends Command
{
    protected $signature = 'sgcore:auto-update
        {--dry-run : Mostra o que seria feito, sem baixar nem aplicar}
        {--no-backup : Aplica via Git sem backup do banco (repassa ao sgcore:update)}';

    protected $description = 'Respeita as preferências do painel: verifica, baixa e/ou instala atualizações';

    public function handle(
        UpdateChecker $checker,
        UpdateRunner $runner,
        UpdatePolicy $policy,
        UpdatePackageStore $store,
        PackageSource $source,
        PackageApply $packageApply,
    ): int {
        if (! config('cms.updater.enabled')) {
            $this->line('Verificador de atualizações desativado.');

            return self::SUCCESS;
        }

        if (! $policy->autoCheck()) {
            $this->line('Verificação automática desativada no painel de atualizações.');

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

        $tag = (string) $latest['tag'];
        $this->info("Auto-update: {$current} → {$tag} disponível.");

        $asset = $checker->packageAsset($latest);
        $packageCapable = $asset !== null
            && (bool) config('cms.updater.apply.enabled')
            && (bool) config('cms.updater.apply.package', true);
        $stored = $store->current();
        $packageReady = $stored !== null && ! Version::isNewer($tag, (string) $stored['version']);

        if ($this->option('dry-run')) {
            $this->line($packageCapable
                ? "Pacote: {$asset['name']} (".round(((int) $asset['size']) / 1048576, 1).' MB)'.($packageReady ? ' — já baixado' : '')
                : 'Sem pacote .zip utilizável nesta release.');
            $this->line('Baixar automaticamente: '.($packageReady ? 'nada a fazer' : ($policy->autoDownload() ? 'sim' : 'não')));
            $this->line('Instalar automaticamente: '.($policy->autoInstall() ? 'sim' : 'não'));

            return self::SUCCESS;
        }

        if (! $packageReady && $packageCapable && ($policy->autoDownload() || $policy->autoInstall())) {
            try {
                $downloaded = $source->fetch($asset);
                $stored = $store->store($downloaded['path'], (string) $latest['version'], (string) $asset['name']);
                $packageReady = true;
                $this->record($current, $tag, 'downloaded');
                $this->line("Pacote {$tag} baixado (".round($stored['bytes'] / 1048576, 1).' MB).');
            } catch (RuntimeException $e) {
                $this->record($current, $tag, 'download_failed');
                Log::warning('sgcore:auto-update download failed', ['to' => $tag, 'error' => $e->getMessage()]);

                $this->error('Falha ao baixar o pacote: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        if (! $policy->autoInstall()) {
            $this->line('Instalação automática desativada; nada foi aplicado.');

            return self::SUCCESS;
        }

        if ($packageCapable && $packageReady) {
            return $this->applyPackage($packageApply, $store, $current, $tag);
        }

        if (! $runner->isEnabled()) {
            $this->error('Auto-update impossível: aplicar pacotes e aplicar via Git estão desativados neste ambiente.');

            return self::FAILURE;
        }

        if (! $runner->isGitCheckout()) {
            $this->error('Auto-update requer uma instalação via Git (sem .git) ou um pacote .zip da Release.');

            return self::FAILURE;
        }

        if (($blockers = $runner->blockers()) !== []) {
            $this->error('Auto-update abortado por bloqueios: '.implode(' · ', $blockers));
            $this->record($current, $tag, 'blocked');
            Log::warning('sgcore:auto-update blocked', ['blockers' => $blockers]);

            return self::FAILURE;
        }

        return $this->applyGit($current, $tag, $runner);
    }

    private function applyPackage(PackageApply $apply, UpdatePackageStore $store, string $from, string $to): int
    {
        $path = $store->path();

        if ($path === null) {
            $this->error('Pacote baixado não encontrado; nada foi aplicado.');

            return self::FAILURE;
        }

        $this->record($from, $to, 'attempting');

        try {
            $apply->apply($path, $from);
            $store->discard();
            $ok = true;
        } catch (Throwable $e) {
            report($e);
            $this->error('A instalação falhou: '.$e->getMessage());
            $ok = false;
        }

        $this->record($from, $to, $ok ? 'updated' : 'failed');
        Log::info('sgcore:auto-update', ['from' => $from, 'to' => $to, 'method' => 'package', 'ok' => $ok]);

        if ($ok) {
            $this->components->info("Atualização automática aplicada: {$from} → {$to}.");
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function applyGit(string $from, string $to, UpdateRunner $runner): int
    {
        $this->record($from, $to, 'attempting');

        $status = $this->call('sgcore:update', array_filter([
            '--to' => $to,
            '--force' => true,
            '--no-backup' => $this->option('no-backup') ?: null,
        ]));

        $ok = $status === self::SUCCESS;

        $this->record($from, $to, $ok ? 'updated' : 'failed');
        Log::info('sgcore:auto-update', ['from' => $from, 'to' => $to, 'method' => 'git', 'ok' => $ok]);

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
            ], 'updater');
        } catch (Throwable) {
            // Recording is best-effort; never block the update on a settings write.
        }
    }
}
