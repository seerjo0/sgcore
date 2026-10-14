<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only "is there a newer release?" checker against GitHub.
 *
 * It never downloads, writes or applies anything: it fetches the latest
 * Release metadata, caches it (to respect GitHub's anonymous rate limit) and
 * compares the tag with the running version. Callers get either null (no newer
 * release / disabled / unreachable) or the release summary with an
 * already-validated, github.com-only URL safe to render as a link.
 */
class UpdateChecker
{
    private const CACHE_KEY = 'sgcore.latest_release';

    /**
     * The release summary currently known, or null when none/newer unavailable.
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
     * Latest release from cache, fetching from GitHub when missing. Fail-soft.
     *
     * @return array<string,string>|null
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

    /**
     * @return array<string,string>|null
     */
    private function fetch(): ?array
    {
        $repo = trim((string) config('cms.updater.repository'));

        if (! $this->isRepository($repo)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'sgcore-updater',
                'Accept' => 'application/vnd.github+json',
            ])
                ->timeout(max(1, (int) config('cms.updater.timeout')))
                ->get("https://api.github.com/repos/{$repo}/releases/latest");
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        if (! is_array($json)) {
            return null;
        }

        $tag = trim((string) ($json['tag_name'] ?? ''));

        if ($tag === '') {
            return null;
        }

        return [
            'tag' => $tag,
            'version' => $this->normalizeVersion($tag),
            'name' => trim((string) ($json['name'] ?? '')) !== '' ? (string) $json['name'] : $tag,
            'url' => $this->safeReleaseUrl((string) ($json['html_url'] ?? ''), $repo),
            'published_at' => (string) ($json['published_at'] ?? ''),
            'body' => (string) ($json['body'] ?? ''),
        ];
    }

    /**
     * Only accept a release URL that is a github.com URL for our repository;
     * anything else (empty, another host, javascript:) yields no link.
     */
    private function safeReleaseUrl(string $url, string $repo): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($url) && $url !== ''
            && strcasecmp((string) $host, 'github.com') === 0
            && is_string($path)
            && str_starts_with($path, '/'.$repo.'/releases')) {
            return $url;
        }

        return '';
    }

    private function isRepository(string $repo): bool
    {
        return preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo) === 1;
    }

    private function normalizeVersion(string $tag): string
    {
        $version = ltrim(trim($tag), 'vV');

        return preg_match('/^\d+(\.\d+)*(?:[-+].*)?$/', $version) === 1 ? $version : $tag;
    }
}
