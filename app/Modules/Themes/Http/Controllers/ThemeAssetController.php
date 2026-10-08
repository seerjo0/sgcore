<?php

namespace App\Modules\Themes\Http\Controllers;

use App\Modules\Themes\Services\ThemeDiscovery;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ThemeAssetController extends Controller
{
    public function __construct(private ThemeDiscovery $discovery) {}

    /**
     * GET /theme-assets/{theme}/{file} — static files shipped with a theme.
     * Traversal attempts and unknown themes return 404.
     */
    public function show(string $theme, string $file): BinaryFileResponse
    {
        $found = $this->discovery->find($theme);

        if ($found === null) {
            abort(404);
        }

        $relative = str_replace('\\', '/', $file);

        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            abort(404);
        }

        $base = realpath($found->path);
        $full = realpath($found->path.'/'.$relative);

        if ($base === false || $full === false || ! is_file($full)) {
            abort(404);
        }

        if (! str_starts_with($full, $base.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $mime = match (strtolower(pathinfo($full, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            default => 'application/octet-stream',
        };

        return response()->file($full, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
