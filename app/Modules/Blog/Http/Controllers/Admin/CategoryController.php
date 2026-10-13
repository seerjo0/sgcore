<?php

namespace App\Modules\Blog\Http\Controllers\Admin;

use App\Modules\Blog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('blog::categories.index', [
            'categories' => Category::query()->withCount('posts')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('blog::categories.form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validate($request);
        $data['slug'] = $this->resolveSlug((string) $data['name'], null);

        Category::query()->create($data);

        return redirect()->route('admin.categorias.index')->with('success', __('blog.created'));
    }

    public function edit(Category $category): View
    {
        return view('blog::categories.form', ['category' => $category]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validate($request, $category);

        $typed = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $typed !== '' ? Str::slug($typed) : $this->resolveSlug((string) $data['name'], $category);

        $category->update($data);

        return redirect()->route('admin.categorias.index')->with('success', __('blog.updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categorias.index')->with('success', __('blog.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validate(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('categories', 'slug')->ignore($category?->id)],
        ], [
            'slug.unique' => __('blog.slug_taken'),
        ]);
    }

    private function resolveSlug(string $name, ?Category $category): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($category !== null, fn ($query) => $query->where('id', '!=', $category->id))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
