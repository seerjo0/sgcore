<?php

namespace App\Modules\Core\Services;

use Illuminate\Support\Str;

/**
 * Immutable view of a module found on disk (bundled in app/Modules or
 * uploaded to the module storage path).
 */
class Module
{
    public const BUNDLED = 'bundled';

    public const UPLOADED = 'uploaded';

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $requires
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $path,
        public readonly array $manifest,
        public readonly string $source,
        public readonly bool $valid,
        public readonly ?string $error = null,
        public readonly array $requires = [],
        public readonly ?string $installedAt = null,
    ) {}

    /**
     * Normalize a folder name into a module slug (lowercase, "_" separator,
     * so it is a valid PSR-4 namespace segment).
     */
    public static function slugFrom(string $folder): string
    {
        return Str::lower(Str::slug(Str::lower($folder), '_'));
    }

    public function name(): string
    {
        $name = $this->manifest['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $this->slug;
    }

    public function description(): string
    {
        $description = $this->manifest['description'] ?? '';

        return is_string($description) ? $description : '';
    }

    public function version(): string
    {
        $version = $this->manifest['version'] ?? '';

        return is_string($version) ? $version : '';
    }

    public function author(): string
    {
        $author = $this->manifest['author'] ?? '';

        return is_string($author) ? $author : '';
    }

    public function isBundled(): bool
    {
        return $this->source === self::BUNDLED;
    }
}
