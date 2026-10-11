<?php

namespace App\Modules\Themes\Http\Controllers;

use App\Modules\PageBuilder\Services\Renderer as BuilderRenderer;
use App\Modules\Pages\Models\Page;
use App\Modules\Themes\Services\ThemeRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class SiteController extends Controller
{
    public function __construct(
        private ThemeRenderer $renderer,
        private BuilderRenderer $builder,
    ) {}

    /**
     * GET / — home page of the active theme (or the builder layout, when the
     * home page has published builder output).
     */
    public function home(): Response
    {
        $page = Page::query()
            ->where('is_home', true)
            ->where('status', 'published')
            ->first();

        if ($page !== null) {
            $built = $this->builder->render($page);

            if ($built !== null) {
                return $this->htmlResponse($built);
            }
        }

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
            $built = $this->builder->render($page);

            if ($built !== null) {
                return $this->htmlResponse($built);
            }

            return $this->renderer->render($page, 'page');
        }

        return $this->notFound();
    }

    private function notFound(): Response
    {
        $built = $this->builder->renderNotFound();

        if ($built !== null) {
            return new Response($built, 404, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return $this->renderer->render(null, '404', 404);
    }

    private function htmlResponse(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
