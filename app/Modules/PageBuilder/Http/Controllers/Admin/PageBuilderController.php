<?php

namespace App\Modules\PageBuilder\Http\Controllers\Admin;

use App\Modules\Core\Services\Settings;
use App\Modules\PageBuilder\Models\BuilderRevision;
use App\Modules\PageBuilder\Services\BuilderSanitizer;
use App\Modules\PageBuilder\Services\ComponentRegistry;
use App\Modules\PageBuilder\Services\Components\ComponentNode;
use App\Modules\PageBuilder\Services\DesignSystem;
use App\Modules\PageBuilder\Services\HtmlLineDiff;
use App\Modules\PageBuilder\Services\RenderContext;
use App\Modules\PageBuilder\Services\Renderer;
use App\Modules\Pages\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PageBuilderController extends Controller
{
    public function __construct(
        private BuilderSanitizer $sanitizer,
        private ComponentRegistry $registry,
        private Renderer $renderer,
        private Settings $settings,
        private HtmlLineDiff $diff,
    ) {}

    /**
     * GET — full-screen editor for the page.
     */
    public function edit(Page $page)
    {
        return view('pagebuilder::editor', [
            'page' => $page,
            'draft' => $page->builder_draft,
            'published' => $page->builder_published,
            'componentsMetadata' => $this->registry->metadata(),
            'designTokens' => app(DesignSystem::class)->editorTokens(),
            'designCss' => app(DesignSystem::class)->css(),
        ]);
    }

    /**
     * POST — store the GrapesJS project as an unpublished draft.
     */
    public function autosave(Request $request, Page $page): JsonResponse
    {
        $data = $request->validate([
            'data' => ['required', 'string', 'max:'.config('pagebuilder.max_json_bytes')],
        ])['data'];

        $project = json_decode($data, true);

        if (! $this->registry->isProject($project)) {
            return response()->json(
                ['errors' => ['data' => __('pagebuilder.invalid_project')]],
                422,
            );
        }

        $page->update(['builder_draft' => $project]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST — publish: typed payloads render through the component registry
     * (allowlist) and are versioned with a revision snapshot; payloads without
     * registered components keep the legacy sanitized-HTML pipeline.
     */
    public function publish(Request $request, Page $page): JsonResponse
    {
        $validated = $request->validate([
            'html' => ['required', 'string', 'max:'.config('pagebuilder.max_html_bytes')],
            'css' => ['required', 'string', 'max:'.config('pagebuilder.max_css_bytes')],
            'json' => ['required', 'string', 'max:'.config('pagebuilder.max_json_bytes')],
        ]);

        $project = json_decode($validated['json'], true);

        if (! $this->registry->isProject($project)) {
            return response()->json(
                ['errors' => ['json' => __('pagebuilder.invalid_project')]],
                422,
            );
        }

        [$tree, $html, $css] = $this->renderProject($page, $project, $validated['html'], $validated['css']);

        if (trim($html) === '') {
            return response()->json(
                ['errors' => ['html' => __('pagebuilder.empty_output')]],
                422,
            );
        }

        $oldVersion = max(1, (int) $page->builder_version);
        $version = $oldVersion + 1;
        $now = now()->toIso8601String();

        $page->update([
            'builder_published' => [
                'version' => config('pagebuilder.schema_version'),
                'published_at' => $now,
                'components' => $tree,
                'html' => $html,
                'css' => $css,
            ],
            'builder_draft' => $project,
            'builder_version' => $version,
        ]);

        BuilderRevision::query()->create([
            'page_id' => $page->id,
            'version' => $version,
            'components' => $tree,
            'html' => $html,
            'css' => $css,
            'project' => $project,
            'created_by' => $request->user()->id,
        ]);

        $this->renderer->forgetPage($page->id, $oldVersion);

        return response()->json([
            'ok' => true,
            'version' => $version,
            'published_at' => $now,
        ]);
    }

    /**
     * POST — preview: render the current editor payload through the same
     * pipeline as publish and return the full document, without persisting or
     * bumping versions. Shown in the editor's preview overlay.
     */
    public function preview(Request $request, Page $page): JsonResponse
    {
        $validated = $request->validate([
            'html' => ['required', 'string', 'max:'.config('pagebuilder.max_html_bytes')],
            'css' => ['required', 'string', 'max:'.config('pagebuilder.max_css_bytes')],
            'json' => ['required', 'string', 'max:'.config('pagebuilder.max_json_bytes')],
        ]);

        $project = json_decode($validated['json'], true);

        if (! $this->registry->isProject($project)) {
            return response()->json(
                ['errors' => ['json' => __('pagebuilder.invalid_project')]],
                422,
            );
        }

        [, $html, $css] = $this->renderProject($page, $project, $validated['html'], $validated['css']);

        return response()->json(['html' => $this->renderer->previewDocument($page, $html, $css)]);
    }

    /**
     * POST — discard the working draft, reverting it to the last published
     * revision (or clearing it when the page was never published).
     */
    public function discard(Request $request, Page $page): JsonResponse
    {
        $revision = $page->revisions()->latest('id')->first();

        $page->update(['builder_draft' => $revision?->project]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST — persist the page-level custom CSS. Stored raw and re-sanitized at
     * render time (defense in depth); the current version's cache is dropped so
     * the next public render rebuilds with the new rules.
     */
    public function saveCss(Request $request, Page $page): JsonResponse
    {
        $validated = $request->validate([
            'css' => ['nullable', 'string', 'max:'.config('pagebuilder.max_css_bytes')],
        ]);

        $page->update(['builder_custom_css' => ($validated['css'] ?? '') !== '' ? $validated['css'] : null]);

        $this->renderer->forgetPage($page->id, max(1, (int) $page->builder_version));

        return response()->json(['ok' => true]);
    }

    /**
     * Shared render pipeline for publish and preview: sanitizes CSS, extracts
     * the typed component tree and renders it (or the sanitized HTML fallback).
     *
     * @param  array<string, mixed>  $project
     * @return array{0: list<ComponentNode>|null, 1: string, 2: string}
     */
    private function renderProject(Page $page, array $project, string $htmlInput, string $cssInput): array
    {
        $css = $this->sanitizer->sanitizeCss($cssInput);
        $tree = $this->registry->extract($project);
        $html = $tree !== null
            ? $this->registry->renderTree($tree, new RenderContext($this->settings, $page))
            : (string) $this->sanitizer->sanitizeHtml($htmlInput);

        return [$tree, $html, $css];
    }

    /**
     * GET — published versions of the page, newest first.
     */
    public function revisions(Page $page): JsonResponse
    {
        $items = $page->revisions()
            ->latest('id')
            ->limit(30)
            ->get(['id', 'version', 'created_at', 'created_by'])
            ->map(fn (BuilderRevision $revision): array => [
                'id' => $revision->id,
                'version' => $revision->version,
                'created_at' => $revision->created_at?->toIso8601String(),
            ]);

        return response()->json(['items' => $items]);
    }

    /**
     * GET — line diff between a stored revision and the currently published
     * version (or the editor draft when nothing is published yet).
     */
    public function revisionDiff(Page $page, BuilderRevision $revision): JsonResponse
    {
        abort_unless($revision->page_id === $page->id, 404);

        $published = is_array($page->builder_published) ? (string) ($page->builder_published['html'] ?? '') : '';
        $current = $published !== '' ? $published : (string) ($this->registry->renderTree(
            $this->registry->extract($page->builder_draft) ?? [],
            new RenderContext($this->settings, $page),
        ));

        return response()->json($this->diff->compare((string) $revision->html, $current));
    }

    /**
     * POST — restore a published version: it becomes the current one through a
     * new version, and the editor draft returns to that revision's project.
     */
    public function restoreRevision(Request $request, Page $page, BuilderRevision $revision): JsonResponse
    {
        abort_unless($revision->page_id === $page->id, 404);

        $oldVersion = max(1, (int) $page->builder_version);
        $version = $oldVersion + 1;
        $now = now()->toIso8601String();

        $page->update([
            'builder_published' => [
                'version' => config('pagebuilder.schema_version'),
                'published_at' => $now,
                'components' => $revision->components,
                'html' => (string) $this->sanitizer->sanitizeHtml((string) $revision->html),
                'css' => $this->sanitizer->sanitizeCss((string) $revision->css),
            ],
            'builder_draft' => $revision->project ?? $page->builder_draft,
            'builder_version' => $version,
        ]);

        BuilderRevision::query()->create([
            'page_id' => $page->id,
            'version' => $version,
            'components' => $revision->components,
            'html' => (string) $this->sanitizer->sanitizeHtml((string) $revision->html),
            'css' => $this->sanitizer->sanitizeCss((string) $revision->css),
            'project' => $revision->project,
            'created_by' => $request->user()->id,
        ]);

        $this->renderer->forgetPage($page->id, $oldVersion);

        return response()->json(['ok' => true, 'version' => $version]);
    }
}
