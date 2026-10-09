<?php

namespace App\Modules\Core\Http\Controllers\Admin;

use App\Modules\Core\Services\Module;
use App\Modules\Core\Services\ModuleDiscovery;
use App\Modules\Core\Services\ModuleImporter;
use App\Modules\Core\Services\ModuleImportException;
use App\Modules\Core\Services\ModuleState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(
        private ModuleDiscovery $discovery,
        private ModuleState $state,
        private ModuleImporter $importer,
    ) {}

    /**
     * GET /admin/modulos — list bundled and uploaded modules.
     */
    public function index(): View
    {
        $modules = $this->discovery->all();
        $inactive = $this->state->inactive();
        $protected = (array) config('cms.modules.protected', []);

        return view('core::admin.modules.index', [
            'modules' => $modules,
            'uploaded' => $modules->reject(fn (Module $module): bool => $module->isBundled()),
            'bundled' => $modules->filter(fn (Module $module): bool => $module->isBundled()),
            'inactive' => $inactive,
            'protected' => $protected,
            'activeCount' => $modules->count(fn (Module $module): bool => ! in_array($module->slug, $inactive, true)),
            'maxUploadMb' => (int) floor(((int) config('cms.modules.max_upload_bytes', 5242880)) / 1048576),
        ]);
    }

    /**
     * POST /admin/modulos — upload and install a module ZIP.
     */
    public function store(Request $request): RedirectResponse
    {
        $maxBytes = (int) config('cms.modules.max_upload_bytes', 5242880);

        $request->validate([
            'module' => ['required', 'file', 'mimes:zip', 'max:'.(int) floor($maxBytes / 1024)],
        ]);

        try {
            $module = $this->importer->import($request->file('module'));
        } catch (ModuleImportException $exception) {
            return back()->withErrors(['module' => $exception->getMessage()]);
        }

        $unsatisfied = $this->inactiveRequirements($module);

        if ($unsatisfied !== []) {
            File::deleteDirectory($module->path);

            return back()->withErrors(['module' => __('modules.requires_inactive', [
                'name' => $module->name(),
                'missing' => implode(', ', $unsatisfied),
            ])]);
        }

        $this->state->rememberInstall($module->slug, $module->version());

        return back()->with('success', __('modules.imported_success', ['name' => $module->name()]));
    }

    /**
     * POST /admin/modulos/{slug}/ativar
     */
    public function activate(Request $request, string $slug): RedirectResponse
    {
        $module = $this->discovery->find($slug);

        if ($module === null) {
            return back()->withErrors(['module' => __('modules.not_found')]);
        }

        if (! $module->valid) {
            return back()->withErrors(['module' => __('modules.manifest_invalid')]);
        }

        $unsatisfied = $this->inactiveRequirements($module);

        if ($unsatisfied !== []) {
            return back()->withErrors(['module' => __('modules.requires_inactive', [
                'name' => $module->name(),
                'missing' => implode(', ', $unsatisfied),
            ])]);
        }

        $this->state->activate($slug);

        return back()->with('success', __('modules.activated', ['name' => $module->name()]));
    }

    /**
     * POST /admin/modulos/{slug}/desativar
     */
    public function deactivate(Request $request, string $slug): RedirectResponse
    {
        $module = $this->discovery->find($slug);

        if ($module === null) {
            return back()->withErrors(['module' => __('modules.not_found')]);
        }

        // Módulos pré-instalados (bundled) são imutáveis: desativar removeria
        // rotas que o layout do admin e o site público dependem (ex.: `themes`
        // derruba o painel inteiro com RouteNotFoundException).
        if ($module->isBundled()) {
            return back()->withErrors(['module' => __('modules.bundled_cannot_deactivate', ['name' => $module->name()])]);
        }

        if ($this->isProtected($slug)) {
            return back()->withErrors(['module' => __('modules.protected_denied', ['name' => $module->name()])]);
        }

        $dependents = $this->activeDependentsOf($slug);

        if ($dependents !== []) {
            return back()->withErrors(['module' => __('modules.required_by', [
                'name' => $module->name(),
                'modules' => implode(', ', $dependents),
            ])]);
        }

        $this->state->deactivate($slug);

        return back()->with('success', __('modules.deactivated', ['name' => $module->name()]));
    }

    /**
     * DELETE /admin/modulos/{slug}
     */
    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $module = $this->discovery->find($slug);

        if ($module === null) {
            return back()->withErrors(['module' => __('modules.not_found')]);
        }

        if ($module->isBundled()) {
            return back()->withErrors(['module' => __('modules.delete_bundled')]);
        }

        if (! $this->state->isInactive($slug)) {
            return back()->withErrors(['module' => __('modules.delete_active', ['name' => $module->name()])]);
        }

        $dependents = $this->activeDependentsOf($slug);

        if ($dependents !== []) {
            return back()->withErrors(['module' => __('modules.required_by', [
                'name' => $module->name(),
                'modules' => implode(', ', $dependents),
            ])]);
        }

        File::deleteDirectory($module->path);
        $this->state->forgetModule($slug);

        return back()->with('success', __('modules.deleted', ['name' => $module->name()]));
    }

    /**
     * Requirements of $module that are missing or inactive on disk.
     *
     * @return list<string>
     */
    private function inactiveRequirements(Module $module): array
    {
        $unsatisfied = [];

        foreach ($module->requires as $dependency) {
            $target = $this->discovery->find($dependency);

            if ($target === null || $this->state->isInactive($dependency)) {
                $unsatisfied[] = $dependency;
            }
        }

        return $unsatisfied;
    }

    /**
     * Names of active modules that declare $slug in their requires.
     *
     * @return list<string>
     */
    private function activeDependentsOf(string $slug): array
    {
        $dependents = [];

        foreach ($this->discovery->all() as $module) {
            if ($module->slug === $slug || $this->state->isInactive($module->slug)) {
                continue;
            }

            foreach ($module->requires as $dependency) {
                if ($dependency === $slug) {
                    $dependents[] = $module->name();

                    break;
                }
            }
        }

        return $dependents;
    }

    private function isProtected(string $slug): bool
    {
        return in_array($slug, (array) config('cms.modules.protected', []), true);
    }
}
