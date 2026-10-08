<?php

namespace App\Modules\Themes\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Extracts a theme ZIP into storage/app/themes/{slug} with safety checks:
 * no path traversal (Zip-Slip), a theme.json must exist and be valid, and the
 * slug must not collide with an existing theme.
 */
class ThemeImporter
{
    public function __construct(private ThemeManifest $manifest) {}

    /**
     * @throws ThemeImportException
     */
    public function import(UploadedFile $file): Theme
    {
        $slug = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($slug === '') {
            throw new ThemeImportException('Não foi possível derivar um identificador do arquivo enviado.');
        }

        $discovery = app(ThemeDiscovery::class);

        if ($discovery->find($slug) !== null) {
            throw new ThemeImportException("Já existe um tema com o identificador \"{$slug}\".");
        }

        $root = $discovery->storagePath();

        if (! is_dir($root)) {
            File::makeDirectory($root, 0755, true, true);
        }

        $temp = $root.'/.import-'.Str::random(16);
        File::makeDirectory($temp, 0755);

        try {
            $zipPath = $file->getRealPath();

            if (! is_string($zipPath) || $zipPath === '' || ! is_file($zipPath)) {
                throw new ThemeImportException('O arquivo enviado não pôde ser lido como ZIP.');
            }

            $this->extract($zipPath, $temp);
            $source = $this->locate($temp);

            [$manifest, $error] = $this->manifest->load($source);

            if ($error !== null) {
                throw new ThemeImportException($error);
            }

            $target = $root.'/'.$slug;

            if (File::isDirectory($target)) {
                throw new ThemeImportException("Já existe um tema com o identificador \"{$slug}\".");
            }

            if ($source === $temp) {
                File::moveDirectory($temp, $target);
            } else {
                File::moveDirectory($source, $target);
                File::deleteDirectory($temp);
            }
        } catch (\Throwable $exception) {
            File::deleteDirectory($temp);

            throw $exception;
        }

        return app(ThemeDiscovery::class)->find($slug)
            ?? new Theme($slug, $root.'/'.$slug, $manifest, false, true);
    }

    /**
     * @throws ThemeImportException
     */
    private function extract(string $zipPath, string $into): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new ThemeImportException('Não foi possível abrir o arquivo ZIP.');
        }

        try {
            $count = $zip->numFiles;

            if ($count === 0) {
                throw new ThemeImportException('O arquivo ZIP está vazio.');
            }

            for ($index = 0; $index < $count; $index++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));

                if (
                    str_starts_with($name, '/')
                    || preg_match('#^[a-zA-Z]:/#', $name) === 1
                    || str_contains($name, '..')
                ) {
                    throw new ThemeImportException('O ZIP contém caminhos inseguros.');
                }
            }

            if (! $zip->extractTo($into)) {
                throw new ThemeImportException('Falha ao extrair o conteúdo do ZIP.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Directory holding theme.json: the extraction root itself, or a single
     * wrapping folder (the common "downloaded as folder" layout).
     *
     * @throws ThemeImportException
     */
    private function locate(string $temp): string
    {
        if (is_file($temp.'/'.ThemeManifest::FILE)) {
            return $temp;
        }

        $entries = array_values(array_filter(
            scandir($temp) ?: [],
            fn (string $entry): bool => $entry !== '.' && $entry !== '..' && ! str_starts_with($entry, '.'),
        ));

        if (
            count($entries) === 1
            && is_dir($temp.'/'.$entries[0])
            && is_file($temp.'/'.$entries[0].'/'.ThemeManifest::FILE)
        ) {
            return $temp.'/'.$entries[0];
        }

        throw new ThemeImportException(
            'O arquivo theme.json não foi encontrado (esperado na raiz do ZIP ou dentro de uma única pasta).',
        );
    }
}
