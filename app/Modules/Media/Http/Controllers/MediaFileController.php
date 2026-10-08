<?php

namespace App\Modules\Media\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaFileController extends Controller
{
    /**
     * Serve a stored image publicly with long-lived cache headers.
     * The path is constrained to the media directory (traversal-proof).
     */
    public function show(string $path): Response
    {
        $disk = Storage::disk('local');
        $base = realpath($disk->path('media'));
        $full = realpath($disk->path('media/'.ltrim($path, '/')));

        if ($base === false || $full === false || ! str_starts_with($full, $base.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        if (! is_file($full)) {
            abort(404);
        }

        return response()->file($full, [
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
