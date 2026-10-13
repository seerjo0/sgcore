<?php

namespace App\Modules\Blog\Http\Controllers;

use App\Modules\Blog\Models\Category;
use App\Modules\Blog\Models\Post;
use App\Modules\Core\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function __construct(private Settings $settings) {}

    /**
     * Public archive of published posts, newest first, optionally filtered by
     * a category slug. Drafts and future-dated posts never appear.
     */
    public function index(Request $request): View
    {
        $siteName = (string) $this->settings->get('site_name', config('app.name'));

        $categorySlug = trim((string) $request->query('categoria', ''));
        $category = $categorySlug !== ''
            ? Category::query()->where('slug', $categorySlug)->first()
            : null;

        $posts = Post::query()
            ->published()
            ->when($category !== null, fn ($query) => $query->where('category_id', $category->id))
            ->with('category')
            ->paginate(10)
            ->withQueryString();

        return view('blog::public.index', [
            'posts' => $posts,
            'category' => $category,
            'categories' => Category::query()->orderBy('name')->get(),
            'siteName' => $siteName,
            'seoTitle' => $category?->name ?: __('blog.title'),
            'seoDescription' => (string) $this->settings->get('seo_description', ''),
        ]);
    }

    /**
     * A single published post; unpublished/missing slugs 404.
     */
    public function show(string $slug): View
    {
        $siteName = (string) $this->settings->get('site_name', config('app.name'));

        $post = Post::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with(['category', 'author', 'cover', 'ogImage'])
            ->firstOrFail();

        $ogImage = null;

        if ($post->ogImage !== null) {
            $ogImage = url($post->ogImage->url());
        } elseif ($post->cover !== null) {
            $ogImage = url($post->cover->url());
        }

        return view('blog::public.show', [
            'post' => $post,
            'siteName' => $siteName,
            'seoTitle' => (string) ($post->seo_title ?: $post->title),
            'seoDescription' => (string) ($post->seo_description ?: $post->excerpt ?: ''),
            'ogImage' => $ogImage ?? '',
            'ogType' => 'article',
        ]);
    }
}
