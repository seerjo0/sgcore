<?php

namespace App\Modules\Blog\Http\Controllers\Admin;

use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Pages\Services\ContentSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $posts = Post::query()
            ->with('category')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($where) use ($q): void {
                    $where->where('title', 'like', "%{$q}%")
                        ->orWhere('slug', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('blog::posts.index', [
            'posts' => $posts,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('blog::posts.form', [
            'post' => null,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, ContentSanitizer $sanitizer): RedirectResponse
    {
        $this->normalizeSlugInput($request);

        $data = $this->validate($request);
        $data = $this->prepare($request, $sanitizer, $data);
        $data['slug'] = $this->resolveSlug($data, null);

        $data['author_id'] = $request->user()->id;

        Post::query()->create($data);

        return redirect()
            ->route('admin.posts.index')
            ->with('success', __('blog.created'));
    }

    public function edit(Post $post): View
    {
        return view('blog::posts.form', [
            'post' => $post,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Post $post, ContentSanitizer $sanitizer): RedirectResponse
    {
        $this->normalizeSlugInput($request);

        $data = $this->validate($request, $post);
        $data = $this->prepare($request, $sanitizer, $data);
        $data['slug'] = $this->resolveSlug($data, $post);

        $post->update($data);

        return redirect()
            ->route('admin.posts.index')
            ->with('success', __('blog.updated'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()
            ->route('admin.posts.index')
            ->with('success', __('blog.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('posts', 'slug')->ignore($post?->id),
            ],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content.body' => ['nullable', 'string', 'max:200000'],
            'cover_image' => ['nullable', 'integer', 'exists:media,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'seo_og_image' => ['nullable', 'integer', 'exists:media,id'],
            'published_at' => ['nullable', 'date'],
        ], [
            'slug.regex' => __('blog.slug_invalid'),
            'slug.unique' => __('blog.slug_taken'),
            'cover_image.exists' => __('blog.media_gone'),
            'seo_og_image.exists' => __('blog.media_gone'),
            'category_id.exists' => __('blog.category_gone'),
        ]);
    }

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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(Request $request, ContentSanitizer $sanitizer, array $data): array
    {
        $data['content'] = ['body' => $sanitizer->sanitize((string) $request->input('content.body'))];
        $data['excerpt'] = $this->nullable($data['excerpt'] ?? null);
        $data['category_id'] = $this->nullable($data['category_id'] ?? null);
        $data['cover_image'] = $this->nullable($data['cover_image'] ?? null);
        $data['seo_og_image'] = $this->nullable($data['seo_og_image'] ?? null);
        $data['published_at'] = $this->nullable($data['published_at'] ?? null);

        if ($data['status'] === 'published' && $data['published_at'] === null) {
            $data['published_at'] = now();
        } elseif ($data['published_at'] !== null) {
            $data['published_at'] = Carbon::parse($data['published_at']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveSlug(array $data, ?Post $post): string
    {
        $typed = (string) ($data['slug'] ?? '');
        $base = $typed !== '' ? $typed : (Str::slug((string) $data['title']) ?: 'post');

        if ($typed === '') {
            return $this->uniqueSlug($base, $post);
        }

        return $base;
    }

    private function uniqueSlug(string $base, ?Post $post): string
    {
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $post)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?Post $post): bool
    {
        return Post::query()
            ->where('slug', $slug)
            ->when($post !== null, fn ($query) => $query->where('id', '!=', $post->id))
            ->exists();
    }

    private function nullable(mixed $value): mixed
    {
        return ($value === '' || $value === null) ? null : $value;
    }
}
