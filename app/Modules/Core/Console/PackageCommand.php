<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\PackageBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class PackageCommand extends Command
{
    protected $signature = 'sgcore:package
        {--version= : Versão do pacote (padrão: config cms.version)}
        {--out= : Caminho do .zip de saída}
        {--skip-composer : Não roda composer install --no-dev no staging}
        {--skip-npm : Não roda npm ci + build no staging (usa public/build atual)}
        {--php-min=8.3 : Versão mínima de PHP gravada no manifesto}';

    protected $description = 'Gera o pacote instalável (zip estilo WordPress) a partir do HEAD, pronto para anexar à Release';

    public function handle(PackageBuilder $builder): int
    {
        $version = trim((string) $this->option('version')) ?: (string) config('cms.version');
        $version = ltrim($version, 'vV');
        $name = (string) config('cms.packager.name', 'sgcore');

        $output = trim((string) $this->option('out'))
            ?: rtrim((string) config('cms.packager.output_dir', base_path('dist')), '/').'/'.$name.'-'.$version.'-public_html.zip';

        $staging = sys_get_temp_dir().'/sgcore-package-'.$version.'-'.bin2hex(random_bytes(4));

        try {
            $this->components->info('Empacotando '.$name.' v'.$version);

            $this->stageFromGit($staging);
            $this->components->info('→ fonte do HEAD extraída');

            if (! $this->option('skip-composer')) {
                $this->runProcess(['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader'], $staging);
                $this->components->info('→ dependências de produção instaladas');
            } else {
                $this->warn('--skip-composer: vendor/ deve existir no staging (copiado da dev).');
                $this->stageVendor($staging);
            }

            if (! $this->option('skip-npm')) {
                $this->runProcess(['npm', 'ci'], $staging);
                $this->runProcess(['npm', 'run', 'build'], $staging);
                $this->components->info('→ assets compilados');
            } else {
                $this->stageBuiltAssets($staging);
            }

            $stats = $builder->assemble($staging, $output, [
                'name' => $name,
                'version' => $version,
                'php_min' => (string) $this->option('php-min'),
            ]);

            $this->components->info(sprintf(
                'Pacote pronto: %s (%d arquivos, %.1f MB)',
                $stats['path'],
                $stats['files'],
                $stats['bytes'] / 1048576,
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error('Falha ao gerar o pacote: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($staging);
        }
    }

    /**
     * @throws Throwable
     */
    private function stageFromGit(string $staging): void
    {
        File::ensureDirectoryExists($staging);

        $zip = $staging.'/.source.zip';

        $process = new Process(['git', '-C', base_path(), 'archive', '--format=zip', '-o', $zip, 'HEAD']);
        $process->setTimeout(120);
        $process->mustRun();

        if (! is_file($zip)) {
            throw new RuntimeException('git archive não produziu saída.');
        }

        $archive = new ZipArchive;

        if ($archive->open($zip) !== true) {
            throw new RuntimeException('Falha ao abrir o archive do git.');
        }

        $archive->extractTo($staging);
        $archive->close();
        @unlink($zip);
    }

    /**
     * Bring the already-installed production vendor into staging (only used with
     * --skip-composer, so the developer can reuse a local build without a full
     * composer run — never copy dev-only packages).
     */
    private function stageVendor(string $staging): void
    {
        $source = base_path('vendor');

        if (! is_dir($source)) {
            throw new RuntimeException('vendor/ ausente; rode sem --skip-composer.');
        }

        File::copyDirectory($source, $staging.'/vendor');
    }

    /** Copy current built assets when --skip-npm is used. */
    private function stageBuiltAssets(string $staging): void
    {
        $source = base_path('public/build');

        if (! is_dir($source)) {
            throw new RuntimeException('public/build ausente; rode npm run build ou sem --skip-npm.');
        }

        File::ensureDirectoryExists($staging.'/public');
        File::copyDirectory($source, $staging.'/public/build');
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command, string $cwd): void
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(600);

        $process->run(function (string $type, string $buffer): void {
            if ($this->output->isVerbose() && $type === Process::OUT) {
                $this->line(rtrim($buffer));
            }
        });

        if (! $process->isSuccessful()) {
            throw new RuntimeException(implode(' ', $command).' falhou: '.trim($process->getErrorOutput()));
        }
    }
}
