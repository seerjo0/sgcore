<?php

namespace App\Modules\PageBuilder\Http\Controllers\Admin;

use App\Modules\Core\Services\Settings;
use App\Modules\PageBuilder\Models\BuilderTemplate;
use App\Modules\PageBuilder\Services\BuilderSanitizer;
use App\Modules\PageBuilder\Services\ComponentRegistry;
use App\Modules\PageBuilder\Services\DesignSystem;
use App\Modules\PageBuilder\Services\RenderContext;
use App\Modules\PageBuilder\Services\Renderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Global builder templates (header/footer/not_found): same component engine and
 * editor as pages, stored per type and applied to every builder document when
 * enabled. Publishing/toggling purges the render cache (theme-builder effect).
 */
class BuilderTemplateController extends Controller
{
    public function __construct(
        private BuilderSanitizer $sanitizer,
        private ComponentRegistry $registry,
        private Renderer $renderer,
        private Settings $settings,
    ) {}

    public function index()
    {
        $types = array_keys(config('pagebuilder.templates'));
        $stored = BuilderTemplate::query()->whereIn('type', $types)->get()->keyBy('type');

        $templates = collect($types)->map(fn (string $type): BuilderTemplate => $stored->get($type)
            ?? new BuilderTemplate(['type' => $type, 'enabled' => false]));

        return view('pagebuilder::templates.index', [
            'templates' => $templates,
            'tokens' => app(DesignSystem::class)->tokens(),
        ]);
    }

    /**
     * POST — persist the global design tokens and invalidate cached renders.
     */
    public function designSave(Request $request)
    {
        $length = ['nullable', 'string', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'];

        $validated = $request->validate([
            'colors.primary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'colors.secondary' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'colors.success' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'colors.danger' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'colors.warning' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'colors.info' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'colors.dark' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'colors.light' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'fonts.body' => ['nullable', 'string', 'max:200'],
            'fonts.heading' => ['nullable', 'string', 'max:200'],
            'spacing.xs' => $length,
            'spacing.sm' => $length,
            'spacing.md' => $length,
            'spacing.lg' => $length,
            'breakpoints.sm' => $length,
            'breakpoints.md' => $length,
            'breakpoints.lg' => $length,
            'breakpoints.xl' => $length,
        ]);

        app(DesignSystem::class)->save($validated);
        $this->renderer->purge();

        return back()->with('success', __('pagebuilder.design_system_saved'));
    }

    public function edit(string $type)
    {
        $this->ensureType($type);
        $template = $this->firstOrNew($type);

        return view('pagebuilder::editor', [
            'template' => $template,
            'draft' => $template->project,
            'published' => $template->components !== null
                ? ['components' => $template->components, 'html' => $template->html, 'css' => $template->css]
                : null,
            'componentsMetadata' => $this->registry->metadata(),
            'designTokens' => app(DesignSystem::class)->editorTokens(),
            'designCss' => app(DesignSystem::class)->css(),
        ]);
    }

    public function autosave(Request $request, string $type): JsonResponse
    {
        $this->ensureType($type);
        $template = $this->firstOrNew($type);

        $data = $request->validate([
            'data' => ['required', 'string', 'max:'.config('pagebuilder.max_json_bytes')],
        ])['data'];

        $project = json_decode($data, true);

        if (! is_array($project) || ! isset($project['components'])) {
            return response()->json(
                ['errors' => ['data' => __('pagebuilder.invalid_project')]],
                422,
            );
        }

        $template->update(['project' => $project]);

        return response()->json(['ok' => true]);
    }

    public function publish(Request $request, string $type): JsonResponse
    {
        $this->ensureType($type);
        $template = $this->firstOrNew($type);

        $validated = $request->validate([
            'html' => ['required', 'string', 'max:'.config('pagebuilder.max_html_bytes')],
            'css' => ['required', 'string', 'max:'.config('pagebuilder.max_css_bytes')],
            'json' => ['required', 'string', 'max:'.config('pagebuilder.max_json_bytes')],
        ]);

        $project = json_decode($validated['json'], true);

        if (! is_array($project) || ! isset($project['components'])) {
            return response()->json(
                ['errors' => ['json' => __('pagebuilder.invalid_project')]],
                422,
            );
        }

        $css = $this->sanitizer->sanitizeCss($validated['css']);
        $tree = $this->registry->extract($project);

        if ($tree === null) {
            return response()->json(
                ['errors' => ['json' => __('pagebuilder.template_needs_components')]],
                422,
            );
        }

        $html = $this->registry->renderTree($tree, new RenderContext($this->settings));

        if (trim($html) === '') {
            return response()->json(
                ['errors' => ['html' => __('pagebuilder.empty_output')]],
                422,
            );
        }

        $template->update([
            'components' => $tree,
            'html' => $html,
            'css' => $css,
            'project' => $project,
            'version' => (int) $template->version + 1,
            'published_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        $this->renderer->purge();

        return response()->json(['ok' => true, 'version' => $template->version]);
    }

    public function toggle(Request $request, string $type): JsonResponse
    {
        $this->ensureType($type);
        $template = $this->firstOrNew($type);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $template->update(['enabled' => (bool) $validated['enabled']]);
        $this->renderer->purge();

        return response()->json(['ok' => true, 'enabled' => (bool) $validated['enabled']]);
    }

    private function ensureType(string $type): void
    {
        abort_unless(in_array($type, array_keys(config('pagebuilder.templates')), true), 404);
    }

    private function firstOrNew(string $type): BuilderTemplate
    {
        return BuilderTemplate::query()->firstOrCreate(
            ['type' => $type],
            ['enabled' => false, 'version' => 1],
        );
    }
}
