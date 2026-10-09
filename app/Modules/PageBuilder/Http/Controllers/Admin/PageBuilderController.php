<?php

namespace App\Modules\PageBuilder\Http\Controllers\Admin;

use App\Modules\PageBuilder\Services\BuilderSanitizer;
use App\Modules\Pages\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PageBuilderController extends Controller
{
    public function __construct(
        private BuilderSanitizer $sanitizer,
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

        if (! is_array($project) || ! isset($project['components'])) {
            return response()->json(
                ['errors' => ['data' => __('pagebuilder.invalid_project')]],
                422,
            );
        }

        $page->update(['builder_draft' => $project]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST — sanitize the builder output and promote it to the public version.
     */
    public function publish(Request $request, Page $page): JsonResponse
    {
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

        $html = (string) $this->sanitizer->sanitizeHtml($validated['html']);
        $css = $this->sanitizer->sanitizeCss($validated['css']);

        if (trim($html) === '') {
            return response()->json(
                ['errors' => ['html' => __('pagebuilder.empty_output')]],
                422,
            );
        }

        $page->update([
            'builder_published' => [
                'html' => $html,
                'css' => $css,
                'json' => $project,
            ],
            'builder_draft' => $project,
        ]);

        return response()->json([
            'ok' => true,
            'published_at' => $page->fresh()->updated_at?->toISOString(),
        ]);
    }
}
