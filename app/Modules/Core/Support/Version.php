<?php

namespace App\Modules\Core\Support;

/**
 * Comparison of release versions with support for the packaging revision
 * suffix (`1.1.0-r1`, `1.1.0-r2`).
 *
 * PHP's version_compare() ranks an unknown qualifier like "r1" *below* the
 * bare version (it treats it as a pre-release), so `1.1.0-r1` would never be
 * offered to an installation running `1.1.0`. A revision is a re-release of
 * the same version, so it must sort after its base and before the next
 * release.
 */
final class Version
{
    /**
     * Returns -1, 0 or 1. Revisions only break ties between equal base
     * versions, so `1.1.0-r9` stays older than `1.1.1`.
     */
    public static function compare(string $a, string $b): int
    {
        [$baseA, $revisionA] = self::split($a);
        [$baseB, $revisionB] = self::split($b);

        return version_compare($baseA, $baseB) ?: ($revisionA <=> $revisionB);
    }

    public static function isNewer(string $candidate, string $current): bool
    {
        return self::compare($candidate, $current) > 0;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function split(string $version): array
    {
        $version = ltrim(trim($version), 'vV');

        if (preg_match('/^(.+)-r(\d+)$/i', $version, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        return [$version, 0];
    }
}
