<?php

namespace App\Modules\Themes\Services;

use App\Modules\Themes\Models\Content;

/**
 * Reads and writes slot values stored in the `contents` table.
 *
 * One row per (scope, theme, page_id) holds the map of slot_id => value.
 * Global rows always use an empty theme so they survive theme switches.
 */
class ContentStore
{
    /**
     * Full slot map for the given coordinates.
     *
     * @return array<string, mixed>
     */
    public function values(string $scope, string $theme, int $pageId): array
    {
        $normalizedTheme = $scope === 'global' ? '' : $theme;

        $content = Content::query()
            ->where('scope', $scope)
            ->where('theme', $normalizedTheme)
            ->where('page_id', $pageId)
            ->first();

        $values = $content?->values;

        return is_array($values) ? $values : [];
    }

    public function get(string $scope, string $theme, int $pageId, string $key, mixed $default = null): mixed
    {
        return $this->values($scope, $theme, $pageId)[$key] ?? $default;
    }

    /**
     * Merge the given slot values into the coordinate (upsert).
     *
     * @param  array<string, mixed>  $values
     */
    public function put(string $scope, string $theme, int $pageId, array $values): void
    {
        $normalizedTheme = $scope === 'global' ? '' : $theme;

        Content::query()->updateOrCreate(
            ['scope' => $scope, 'theme' => $normalizedTheme, 'page_id' => $pageId],
            ['values' => array_merge($this->values($scope, $theme, $pageId), $values)],
        );
    }

    /**
     * Drop a single slot value.
     */
    public function forget(string $scope, string $theme, int $pageId, string $key): void
    {
        $values = $this->values($scope, $theme, $pageId);

        if (! array_key_exists($key, $values)) {
            return;
        }

        unset($values[$key]);

        $normalizedTheme = $scope === 'global' ? '' : $theme;

        if ($values === []) {
            Content::query()
                ->where('scope', $scope)
                ->where('theme', $normalizedTheme)
                ->where('page_id', $pageId)
                ->delete();

            return;
        }

        Content::query()->updateOrCreate(
            ['scope' => $scope, 'theme' => $normalizedTheme, 'page_id' => $pageId],
            ['values' => $values],
        );
    }
}
