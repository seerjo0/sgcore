<?php

namespace App\Modules\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Extracts a module ZIP into storage/app/modules/{slug} with safety checks:
 * no path traversal (Zip-Slip), a module.json must exist and be valid, and a
 * Providers/*ServiceProvider.php must be present so the module can register
 * itself (provider, routes, migrations and views) like any bundled module.
 */
class ModuleImporter
{
    public function __construct(
        private ModuleManifest $manifest,
        private ModuleDiscovery $discovery,
    ) {}

    /**
     * @throws ModuleImportException
     */
    public function import(UploadedFile $file): Module
    {
        $candidate = Module::slugFrom(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($candidate === '') {
            throw new ModuleImportException('Não foi possível derivar um identificador do arquivo enviado.');
        }

        $existing = $this->discovery->find($candidate);

        // Reenviar o ZIP de um módulo importado ATUALIZA aquele módulo. Módulos
        // embutidos (o core) nunca são substituídos por upload.
        if ($existing !== null && $existing->isBundled()) {
            throw new ModuleImportException("O módulo embutido \"{$candidate}\" não pode ser substituído por upload.");
        }

        $root = $this->discovery->storagePath();

        if (! is_dir($root)) {
            File::makeDirectory($root, 0755, true, true);
        }

        $temp = $root.'/.import-'.Str::random(16);
        File::makeDirectory($temp, 0755);

        try {
            $zipPath = $file->getRealPath();

            if (! is_string($zipPath) || $zipPath === '' || ! is_file($zipPath)) {
                throw new ModuleImportException('O arquivo enviado não pôde ser lido como ZIP.');
            }

            $this->extract($zipPath, $temp);
            $source = $this->locate($temp);

            [$manifest, $error] = $this->manifest->load($source);

            if ($error !== null) {
                throw new ModuleImportException($error);
            }

            $slug = isset($manifest['slug'])
                ? (string) $manifest['slug']
                : $candidate;

            if (preg_match(ModuleManifest::SLUG_PATTERN, $slug) !== 1) {
                throw new ModuleImportException('O "slug" do módulo precisa ser um identificador válido (minúsculas, números e "_").');
            }

            if (glob(rtrim($source, '/\\').'/Providers/*ServiceProvider.php') === []) {
                throw new ModuleImportException('O módulo não possui um Providers/*ServiceProvider.php.');
            }

            $target = $root.'/'.$slug;
            $existingTarget = $slug === $candidate ? $existing : $this->discovery->find($slug);
            $replaced = $existingTarget !== null;

            if ($replaced) {
                if ($existingTarget->isBundled()) {
                    throw new ModuleImportException("O módulo embutido \"{$slug}\" não pode ser substituído por upload.");
                }

                if (! File::isDirectory($target)) {
                    // módulo conhecido mas pasta sumiu do disco — instalar do zero
                    $replaced = false;
                }
            }

            if (File::isDirectory($target) && ! $replaced) {
                throw new ModuleImportException("Já existe um módulo com o identificador \"{$slug}\".");
            }

            // Atualização: Dependências precisam continuar satisfeitas ANTES
            // de destruir a versão boa; e a pasta antiga sai primeiro, senão o
            // copyDirectory deixaria arquivos órfãos do ZIP anterior.
            if ($replaced) {
                $state = app(ModuleState::class);

                foreach (array_values(array_filter((array) ($manifest['requires'] ?? []), 'is_string')) as $dependency) {
                    if ($this->discovery->find($dependency) === null || $state->isInactive($dependency)) {
                        throw new ModuleImportException(__('modules.requires_inactive', [
                            'name' => (string) ($manifest['name'] ?? $slug),
                            'missing' => $dependency,
                        ]));
                    }
                }

                File::deleteDirectory($target);
            }

            if ($source === $temp) {
                $this->place($temp, $target);
            } else {
                $this->place($source, $target);
                File::deleteDirectory($temp);
            }
        } catch (\Throwable $exception) {
            File::deleteDirectory($temp);

            throw $exception;
        }

        $module = $this->discovery->find($slug)
            ?? new Module($slug, $target, $manifest, Module::UPLOADED, true);

        return $module->withUpdated($replaced ?? false);
    }

    /**
     * Copia $from para $target. Copiar (em vez de renomear/mover) é tolerante
     * a manipuladores abertos de antivírus no Windows, que tornam o rename
     * intermitentemente falho.
     */
    private function place(string $from, string $target): void
    {
        File::copyDirectory($from, $target);
        File::deleteDirectory($from);
    }

    /**
     * @throws ModuleImportException
     */
    private function extract(string $zipPath, string $into): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new ModuleImportException('Não foi possível abrir o arquivo ZIP.');
        }

        try {
            $count = $zip->numFiles;

            if ($count === 0) {
                throw new ModuleImportException('O arquivo ZIP está vazio.');
            }

            for ($index = 0; $index < $count; $index++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));

                if (
                    str_starts_with($name, '/')
                    || preg_match('#^[a-zA-Z]:/#', $name) === 1
                    || str_contains($name, '..')
                ) {
                    throw new ModuleImportException('O ZIP contém caminhos inseguros.');
                }
            }

            if (! $zip->extractTo($into)) {
                throw new ModuleImportException('Falha ao extrair o conteúdo do ZIP.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Directory holding module.json: the extraction root itself, or a single
     * wrapping folder (the common "downloaded as folder" layout).
     *
     * @throws ModuleImportException
     */
    private function locate(string $temp): string
    {
        if (is_file($temp.'/'.ModuleManifest::FILE)) {
            return $temp;
        }

        $entries = array_values(array_filter(
            scandir($temp) ?: [],
            fn (string $entry): bool => $entry !== '.' && $entry !== '..' && ! str_starts_with($entry, '.'),
        ));

        if (
            count($entries) === 1
            && is_dir($temp.'/'.$entries[0])
            && is_file($temp.'/'.$entries[0].'/'.ModuleManifest::FILE)
        ) {
            return $temp.'/'.$entries[0];
        }

        throw new ModuleImportException(
            'O arquivo module.json não foi encontrado (esperado na raiz do ZIP ou dentro de uma única pasta).',
        );
    }
}
