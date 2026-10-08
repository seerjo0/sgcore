<?php

namespace App\Modules\Themes\Http\Controllers;

use App\Modules\Pages\Models\Page;
use App\Modules\Themes\Services\ThemeRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SiteController extends Controller
{
    public function __construct(private ThemeRenderer $renderer) {}

    /**
     * GET / — home page of the active theme.
     */
    public function home(): Response
    {
        $page = Page::query()
            ->where('is_home', true)
            ->where('status', 'published')
            ->first();

        return $this->renderer->render($page, 'home');
    }

    /**
     * Route::fallback — resolve any URI no other route claimed (matched last
     * by the router, so /admin, /instalar, /media… always win).
     */
    public function fallback(Request $request): Response
    {
        $path = $request->path();

        if ($path === '' || $path === '/') {
            return $this->home();
        }

        return $this->resolve($path);
    }

    /**
     * Resolve a published page by slug or show the 404 template.
     */
    public function resolve(string $any): Response
    {
        if ($any === '' || $any === '/') {
            return $this->home();
        }

        if (str_contains($any, '/') || str_contains($any, '\\')) {
            return $this->notFound();
        }

        $page = Page::query()
            ->where('slug', $any)
            ->where('status', 'published')
            ->first();

        if ($page !== null) {
            return $this->renderer->render($page, 'page');
        }

        return $this->notFound();
    }

    private function notFound(): Response
    {
        return $this->renderer->render(null, '404', 404);
    }
}
