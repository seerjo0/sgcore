<?php

namespace App\Modules\Pages\Http\Controllers\Admin;

use App\Modules\Pages\Models\Page;
use App\Modules\Pages\Services\ContentSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Page listing with search.
     */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $pages = Page::query()
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($where) use ($q): void {
                    $where->where('title', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('is_home')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pages::index', [
            'pages' => $pages,
            'q' => $q,
        ]);
    }

    /**
     * Show the page creation form.
     */
    public function create(): View
    {
        return view('pages::form', [
            'page' => null,
        ]);
    }

    /**
     * Persist a new page (auto slug when the field is left blank).
     */
    public function store(Request $request, ContentSanitizer $sanitizer): RedirectResponse
    {
        $this->normalizeSlugInput($request);

        $data = $this->validate($request);

        $typed = (string) ($data['slug'] ?? '');
        $slug = $typed !== '' ? $typed : Str::slug((string) $data['title']);
        $slug = $slug !== '' ? $slug : 'pagina';

        if ($typed === '') {
            $slug = $this->uniqueSlug($slug, null);
        } elseif ($error = $this->slugError($slug, null)) {
            return back()->withErrors(['slug' => $error])->withInput();
        }

        $data = $this->prepare($request, $sanitizer, $data);
        $data['slug'] = $slug;

        DB::transaction(function () use ($data): void {
            if ($data['is_home']) {
                $this->demoteOtherHomes(null);
            }

            Page::create($data);
        });

        return redirect()
            ->route('admin.paginas.index')
            ->with('success', 'Página criada com sucesso.');
    }

    /**
     * Show the page editor.
     */
    public function edit(Page $page): View
    {
        return view('pages::form', [
            'page' => $page,
        ]);
    }

    /**
     * Update an existing page.
     */
    public function update(Request $request, Page $page, ContentSanitizer $sanitizer): RedirectResponse
    {
        $this->normalizeSlugInput($request);

        $data = $this->validate($request, $page);

        $typed = (string) ($data['slug'] ?? '');
        $slug = $typed !== '' ? $typed : Str::slug((string) $data['title']);
        $slug = $slug !== '' ? $slug : 'pagina';

        if ($typed === '') {
            $slug = $this->uniqueSlug($slug, $page);
        } elseif ($error = $this->slugError($slug, $page)) {
            return back()->withErrors(['slug' => $error])->withInput();
        }

        $data = $this->prepare($request, $sanitizer, $data);
        $data['slug'] = $slug;

        DB::transaction(function () use ($data, $page): void {
            if ($data['is_home']) {
                $this->demoteOtherHomes($page);
            }

            $page->update($data);
        });

        return redirect()
            ->route('admin.paginas.index')
            ->with('success', 'Página atualizada com sucesso.');
    }

    /**
     * Delete the page (menu items pointing at it are nulled by the FK).
     */
    public function destroy(Page $page): RedirectResponse
    {
        $page->delete();

        return redirect()
            ->route('admin.paginas.index')
            ->with('success', 'Página excluída com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_home' => ['sometimes', 'boolean'],
            'content.body' => ['nullable', 'string', 'max:200000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'seo_og_image' => ['nullable', 'integer', 'exists:media,id'],
            'published_at' => ['nullable', 'date'],
        ], [
            'slug.regex' => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'seo_og_image.exists' => 'A imagem selecionada não existe mais na biblioteca.',
        ]);
    }

    /**
     * Slug typed by the user is slugged automatically ("" → null keeps the
     * auto-generation path based on the title).
     */
    private function normalizeSlugInput(Request $request): void
    {
        $raw = trim((string) $request->input('slug'));

        if ($raw === '') {
            return;
        }

        $slug = Str::slug($raw);
        $request->merge(['slug' => $slug !== '' ? $slug : null]);
    }

    /**
     * Fill the model attributes that are not straight request input.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, ContentSanitizer $sanitizer, array $data): array
    {
        $data['content'] = ['body' => $sanitizer->sanitize((string) $request->input('content.body'))];
        $data['is_home'] = $request->boolean('is_home');
        $data['published_at'] = $data['published_at'] ?? null;

        if ($data['status'] === 'published' && $data['published_at'] === null) {
            $data['published_at'] = now();
        } elseif ($data['published_at'] !== null) {
            $data['published_at'] = Carbon::parse($data['published_at']);
        }

        return $data;
    }

    private function uniqueSlug(string $base, ?Page $page): string
    {
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, Page::RESERVED_SLUGS, true) || $this->slugTaken($slug, $page)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function slugError(string $slug, ?Page $page): ?string
    {
        if (in_array($slug, Page::RESERVED_SLUGS, true)) {
            return 'Este slug é reservado pelo sistema. Escolha outro.';
        }

        if ($this->slugTaken($slug, $page)) {
            return 'Já existe uma página com este slug.';
        }

        return null;
    }

    private function slugTaken(string $slug, ?Page $page): bool
    {
        return Page::query()
            ->where('slug', $slug)
            ->when($page !== null, fn ($query) => $query->where('id', '!=', $page->id))
            ->exists();
    }

    /**
     * Only one page can be the home page; the others are demoted first.
     */
    private function demoteOtherHomes(?Page $page): void
    {
        Page::query()
            ->where('is_home', true)
            ->when($page !== null, fn ($query) => $query->whereKeyNot($page->id))
            ->update(['is_home' => false]);
    }
}
