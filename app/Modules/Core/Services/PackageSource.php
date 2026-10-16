<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Downloads a release package asset safely: the URL must point at the
 * configured repository on github.com / objects.githubusercontent.com, the size
 * must respect the configured ceiling, and when GitHub ships a sha256 digest we
 * verify it. Returns a local temp .zip path the caller hands to PackageUpdater.
 */
class PackageSource
{
    /**
     * @param  array{name:string,url:string,size:int,digest:string}  $asset
     * @return array{path:string,bytes:int}
     */
    public function fetch(array $asset): array
    {
        $url = (string) ($asset['url'] ?? '');
        $host = parse_url($url, PHP_URL_HOST);
        $repo = trim((string) config('cms.updater.repository'));

        $allowed = is_string($host)
            && (strcasecmp($host, 'github.com') === 0 || str_ends_with(strtolower($host), 'githubusercontent.com'));

        if (! $allowed || $repo === '' || ! str_contains(str_replace('\\', '/', $url), '/'.trim($repo, '/').'/')) {
            throw new RuntimeException('URL do pacote fora do repositório permitido.');
        }

        $max = (int) config('cms.updater.apply.max_package_bytes', 200 * 1024 * 1024);

        if ($max > 0 && (int) ($asset['size'] ?? 0) > $max) {
            throw new RuntimeException('O pacote excede o tamanho máximo permitido ('.round($max / 1048576, 1).' MB).');
        }

        $temp = tempnam(sys_get_temp_dir(), 'sgcore-pkg-').'.zip';

        try {
            $response = Http::withHeaders(['User-Agent' => 'sgcore-updater'])
                ->timeout((int) config('cms.updater.apply.download_timeout', 120))
                ->throw()
                ->get($url);

            file_put_contents($temp, $response->body());
        } catch (Throwable $e) {
            @unlink($temp);

            throw new RuntimeException('Falha ao baixar o pacote: '.$e->getMessage(), 0, $e);
        }

        $bytes = (int) @filesize($temp);

        if ($bytes <= 0) {
            @unlink($temp);

            throw new RuntimeException('Download do pacote vazio.');
        }

        $digest = (string) ($asset['digest'] ?? '');

        if ($digest !== '' && ! hash_equals($digest, (string) hash_file('sha256', $temp))) {
            @unlink($temp);

            throw new RuntimeException('Checksum do pacote não confere com o da Release.');
        }

        return ['path' => $temp, 'bytes' => $bytes];
    }
}
