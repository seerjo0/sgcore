<?php

namespace App\Modules\Themes\Http\Controllers\Admin;

use App\Modules\Core\Services\Settings;
use App\Modules\Themes\Models\Content;
use App\Modules\Themes\Services\ThemeDiscovery;
use App\Modules\Themes\Services\ThemeImporter;
use App\Modules\Themes\Services\ThemeImportException;
use App\Modules\Themes\Services\ThemePageImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    public function __construct(
        private ThemeDiscovery $discovery,
        private ThemeImporter $importer,
        private ThemePageImporter $pageImporter,
        private Settings $settings,
    ) {}

    /**
     * GET /admin/aparencia — list bundled and imported themes.
     */
    public function index(): View
    {
        return view('themes::admin.appearance.index', [
            'themes' => $this->discovery->all(),
            'active' => (string) $this->settings->get('active_theme', config('cms.themes.default_theme', 'classic')),
            'maxUploadMb' => (int) floor(((int) config('cms.installer.max_theme_upload_bytes', 10485760)) / 1048576),
        ]);
    }

    /**
     * POST /admin/aparencia — import a theme ZIP.
     */
    public function store(Request $request): RedirectResponse
    {
        $maxBytes = (int) config('cms.installer.max_theme_upload_bytes', 10485760);

        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:'.(int) floor($maxBytes / 1024)],
        ]);

        try {
            $theme = $this->importer->import($request->file('file'));
        } catch (ThemeImportException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $message = $theme->updated
            ? __('themes.updated_success', ['name' => $theme->name()])
            : __('themes.imported_success', ['name' => $theme->name()]);

        return back()->with('success', $message);
    }

    /**
     * POST /admin/aparencia/{slug}/excluir — remove an imported theme.
     *
     * Guardas: temas embutidos são imutáveis; o tema ativo continua no ar
     * até outro ser ativado (um site sem tema = 500); um ZIP corrompido
     * (tema inválido) pode — e deve — ser excluído/reenviado.
     */
    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $theme = $this->discovery->find($slug);

        if ($theme === null) {
            return back()->withErrors(['theme' => __('themes.delete_missing')]);
        }

        if ($theme->builtin) {
            return back()->withErrors(['theme' => __('themes.delete_bundled', ['name' => $theme->name()])]);
        }

        $active = (string) $this->settings->get('active_theme', config('cms.themes.default_theme', 'classic'));

        if ($active === $theme->slug) {
            return back()->withErrors(['theme' => __('themes.delete_active', ['name' => $theme->name()])]);
        }

        File::deleteDirectory($theme->path);

        // Valores de slots gravados por tema (ContentStore) — sem limpeza,
        // virariam linhas órfãs para sempre.
        Content::query()->where('theme', $theme->slug)->delete();

        return back()->with('success', __('themes.deleted', ['name' => $theme->name()]));
    }

    /**
     * POST /admin/aparencia/ativar — set the active theme.
     */
    public function activate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100'],
        ]);

        $theme = $this->discovery->find($validated['slug']);

        if ($theme === null || ! $theme->valid) {
            return back()->withErrors(['slug' => __('themes.activate_invalid')]);
        }

        $this->settings->set('active_theme', $theme->slug, 'general');

        return back()->with('success', __('themes.activated', ['name' => $theme->name()]));
    }

    /**
     * POST /admin/aparencia/importar — convert a theme template (or every
     * template the theme exposes, with template=all) into editable Site
     * Builder pages that also appear in the Páginas listing.
     */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'max:100'],
            'template' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        try {
            if ($validated['template'] === 'all') {
                $pages = $this->pageImporter->importAll(
                    $validated['theme'],
                    $validated['title'],
                );

                return redirect()
                    ->route('admin.paginas.index')
                    ->with('success', __('themes.imported_all', ['count' => count($pages)]));
            }

            $page = $this->pageImporter->importToPage(
                $validated['theme'],
                $validated['template'],
                $validated['title'],
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['theme' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.paginas.builder.index', $page)
            ->with('success', __('themes.imported_page', ['title' => $page->title]));
    }
}
