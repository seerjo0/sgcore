<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * "Is there a newer version?" checker against GitHub.
 *
 * Read-only by itself: it fetches the latest Release (falling back to the
 * newest Git tag when the repository has no Releases yet), caches the outcome
 * to respect GitHub's anonymous rate limit, and compares it with the running
 * version. Callers get either null (nothing newer / disabled / unreachable) or
 * a summary whose URL is guaranteed to be a github.com URL for our repository.
 * Applying updates lives in UpdateRunner, which is gated separately.
 */
class UpdateChecker
{
    private const CACHE_KEY = 'sgcore.latest_release';

    private ?string $lastError = null;

    /**
     * Reason the last fetch failed (network/SSL/HTTP status), if any.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * The newest release/tag known, or null when none/newer unavailable.
     *
     * @return array{tag:string,version:string,name:string,url:string,published_at:string,body:string}|null
     */
    public function updateAvailable(bool $mayFetch = true): ?array
    {
        if (! config('cms.updater.enabled')) {
            return null;
        }

        $release = $mayFetch ? $this->latest() : $this->cached();

        if ($release === null) {
            return null;
        }

        return version_compare($release['version'], $this->currentVersion(), '>') ? $release : null;
    }

    public function currentVersion(): string
    {
        return (string) config('cms.version');
    }

    /**
     * Cached latest release (no network). Null if never fetched.
     *
     * @return array<string,string>|null
     */
    public function cached(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @return array{tag:string,version:string,name:string,url:string,published_at:string,body:string}|null
     */
    public function latest(bool $force = false): ?array
    {
        if ($force) {
            Cache::forget(self::CACHE_KEY);
        }

        $ttl = max(60, (int) config('cms.updater.cache_ttl'));

        $result = Cache::remember(self::CACHE_KEY, $ttl, fn (): ?array => $this->fetch());

        return is_array($result) ? $result : null;
    }

    public static function normalizeVersion(string $tag): string
    {
        $version = ltrim(trim($tag), 'vV');

        return preg_match('/^\d+(\.\d+)*(?:[-+].*)?$/', $version) === 1 ? $version : $tag;
    }

    public static function isRepository(string $repo): bool
    {
        return preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo) === 1;
    }

    /**
     * @return array{tag:string,version:string,name:string,url:string,published_at:string,body:string}|null
     */
    private function fetch(): ?array
    {
        $this->lastError = null;

        $repo = trim((string) config('cms.updater.repository'));

        if (! self::isRepository($repo)) {
            return null;
        }

        $source = (string) config('cms.updater.source', 'auto');

        if ($source === 'tags') {
            return $this->fromTags($repo);
        }

        return $this->fromReleases($repo) ?? $this->fromTags($repo);
    }

    /**
     * @return array<string,string>|null
     */
    private function fromReleases(string $repo): ?array
    {
        $json = $this->get("https://api.github.com/repos/{$repo}/releases/latest");

        if (! is_array($json)) {
            return null;
        }

        $tag = trim((string) ($json['tag_name'] ?? ''));

        if ($tag === '') {
            return null;
        }

        return [
            'tag' => $tag,
            'version' => self::normalizeVersion($tag),
            'name' => trim((string) ($json['name'] ?? '')) !== '' ? (string) $json['name'] : $tag,
            'url' => $this->safeReleaseUrl((string) ($json['html_url'] ?? ''), $repo),
            'published_at' => (string) ($json['published_at'] ?? ''),
            'body' => (string) ($json['body'] ?? ''),
            'assets' => $this->normalizeAssets(is_array($json['assets'] ?? null) ? $json['assets'] : [], $repo),
        ];
    }

    /**
     * The .zip package asset of a release (name ends with .zip, hosted on a
     * github asset CDN), with its size and sha256 digest when GitHub provides
     * them. Null when the release has no downloadable package.
     *
     * @param  array<string,mixed>|null  $release
     * @return array{name:string,url:string,size:int,digest:string}|null
     */
    public function packageAsset(?array $release): ?array
    {
        if ($release === null) {
            return null;
        }

        foreach ((array) ($release['assets'] ?? []) as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            // Assets may arrive normalized (keys url/digest-hex) or raw (browser_download_url/sha256:hex).
            $url = (string) ($asset['url'] ?? $asset['browser_download_url'] ?? '');
            $name = (string) ($asset['name'] ?? '');
            $host = parse_url($url, PHP_URL_HOST);

            if ($url !== ''
                && str_ends_with(strtolower($name), '.zip')
                && is_string($host)
                && (strcasecmp($host, 'github.com') === 0 || str_ends_with(strtolower($host), 'githubusercontent.com'))) {
                return [
                    'name' => $name,
                    'url' => $url,
                    'size' => (int) ($asset['size'] ?? 0),
                    'digest' => self::extractDigestHex((string) ($asset['digest'] ?? '')),
                ];
            }
        }

        return null;
    }

    /**
     * Accepts either a bare sha256 hex or GitHub's "sha256:<hex>" form; returns
     * lowercase hex, or empty string when it is not a valid 64-char hex digest.
     */
    private static function extractDigestHex(string $digest): string
    {
        $hex = strtolower(trim($digest));
        $hex = str_starts_with($hex, 'sha256:') ? substr($hex, 7) : $hex;

        return preg_match('/^[0-9a-f]{64}$/', $hex) === 1 ? $hex : '';
    }

    /**
     * @param  array<int,mixed>  $assets
     * @return list<array{name:string,url:string,size:int,digest:string}>
     */
    private function normalizeAssets(array $assets, string $repo): array
    {
        $out = [];

        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $url = (string) ($asset['browser_download_url'] ?? '');
            $host = parse_url($url, PHP_URL_HOST);

            if ($url === '' || ! is_string($host)
                || ! (strcasecmp($host, 'github.com') === 0 || str_ends_with(strtolower($host), 'githubusercontent.com'))) {
                continue;
            }

            $out[] = [
                'name' => (string) ($asset['name'] ?? ''),
                'url' => $url,
                'size' => (int) ($asset['size'] ?? 0),
                'digest' => $this->normalizeDigest((string) ($asset['digest'] ?? '')),
            ];
        }

        return $out;
    }

    /** GitHub ships "sha256:<hex>"; keep only the hex, empty when absent/invalid. */
    private function normalizeDigest(string $digest): string
    {
        $hex = strtolower(ltrim($digest, ' '));
        $hex = str_starts_with($hex, 'sha256:') ? substr($hex, 7) : '';

        return preg_match('/^[0-9a-f]{64}$/', $hex) === 1 ? $hex : '';
    }

    /**
     * Pick the newest semver from the repository's Git tags and build a safe
     * tag URL ourselves (so activation is just `git tag` + `git push`).
     *
     * @return array<string,string>|null
     */
    private function fromTags(string $repo): ?array
    {
        $json = $this->get("https://api.github.com/repos/{$repo}/tags?per_page=100");

        if (! is_array($json)) {
            return null;
        }

        $best = null;

        foreach ($json as $item) {
            $tag = trim((string) (is_array($item) ? ($item['name'] ?? '') : ''));

            if ($tag === '') {
                continue;
            }

            $version = self::normalizeVersion($tag);

            if (preg_match('/^\d+(\.\d+)*(?:[-+].*)?$/', $version) !== 1) {
                continue;
            }

            if ($best === null || version_compare($version, $best['version'], '>')) {
                $best = ['tag' => $tag, 'version' => $version];
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'tag' => $best['tag'],
            'version' => $best['version'],
            'name' => $best['tag'],
            'url' => 'https://github.com/'.$repo.'/releases/tag/'.$best['tag'],
            'published_at' => '',
            'body' => '',
            'assets' => [],
        ];
    }

    /**
     * @return array<int|string,mixed>|null
     */
    private function get(string $url): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'sgcore-updater',
                'Accept' => 'application/vnd.github+json',
            ])
                ->timeout(max(1, (int) config('cms.updater.timeout')))
                ->get($url);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();

            return null;
        }

        if (! $response->successful()) {
            if ($response->status() !== 404) {
                $this->lastError = 'HTTP '.$response->status();
            }

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * Only accept a github.com URL for our repository's releases; anything else
     * (empty, another host, javascript:) yields no link.
     */
    private function safeReleaseUrl(string $url, string $repo): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if ($url !== ''
            && strcasecmp((string) $host, 'github.com') === 0
            && is_string($path)
            && str_starts_with($path, '/'.$repo.'/releases')) {
            return $url;
        }

        return '';
    }
}
