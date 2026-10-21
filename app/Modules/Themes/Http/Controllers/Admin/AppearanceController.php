<?php

namespace App\Modules\Themes\Http\Controllers\Admin;

use App\Modules\Core\Services\Settings;
use App\Modules\Themes\Services\ThemeDiscovery;
use App\Modules\Themes\Services\ThemeImporter;
use App\Modules\Themes\Services\ThemeImportException;
use App\Modules\Themes\Services\ThemePageImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        return back()->with('success', __('themes.imported_success', ['name' => $theme->name()]));
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
     * POST /admin/aparencia/importar — convert a theme template into an
     * editable Site Builder page and open it in the editor.
     */
    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'max:100'],
            'template' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        try {
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
