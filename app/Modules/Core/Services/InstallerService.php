<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Throwable;

class InstallerService
{
    public function __construct(
        private InstallStepRegistry $steps,
        private InstallerLock $lock,
    ) {}

    /**
     * Run migrations, every registered install step and finally write the lock.
     *
     * @param  array<string, mixed>  $context
     */
    public function install(array $context): void
    {
        $exitCode = Artisan::call('migrate', ['--force' => true]);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Falha ao executar as migrations:'.PHP_EOL.Artisan::output()
            );
        }

        $this->createStorageLink();

        foreach ($this->steps->all() as $step) {
            try {
                $step->run($context);
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    "Falha no passo de instalação \"{$step->name()}\": {$exception->getMessage()}",
                    previous: $exception,
                );
            }
        }

        $this->lock->create([
            'app' => 'sgcore',
            'site_name' => (string) ($context['site_name'] ?? ''),
        ]);
    }

    /**
     * Whether every step has already been executed (used to resume safely).
     */
    public function isInstalled(): bool
    {
        return $this->lock->exists();
    }

    private function createStorageLink(): void
    {
        if (! config('cms.installer.create_storage_link', true)) {
            return;
        }

        $link = public_path('storage');

        if (file_exists($link)) {
            return;
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable) {
            // Symlinks are unavailable on some hosts; media still works via
            // the storage route fallback. Never block the installation.
        }
    }
}
