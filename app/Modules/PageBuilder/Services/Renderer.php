<?php

namespace App\Modules\PageBuilder\Services;

use App\Modules\Core\Services\Settings;
use App\Modules\PageBuilder\Models\BuilderTemplate;
use App\Modules\PageBuilder\Services\Components\ComponentNode;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Renders a published builder page as a standalone Bootstrap 5 document.
 *
 * Two payload shapes are supported (backward compatibility by construction):
 *
 *  - structured: `builder_published.components` holds the validated component
 *    tree extracted at publish time; it is re-rendered here through the
 *    ComponentRegistry (allowlist) so direct DB edits never emit arbitrary HTML
 *    through unregistered types, and the result is cached per page version;
 *  - legacy: pages published before the component engine stored plain
 *    sanitized `html`/`css`, which keeps rendering through the old path.
 *
 * Site header/footer come from enabled builder templates when present, falling
 * back to the CMS chrome (primary/footer menus). A full document is cached by
 * {page, version, purge epoch} — publishing a page bumps its version, while
 * templates and the design system bump the global epoch.
 */
class Renderer
{
    private const EPOCH_KEY = 'pb-purge-epoch';

    public function __construct(
        private Settings $settings,
        private BuilderSanitizer $sanitizer,
        private ComponentRegistry $registry,
        private DesignSystem $design,
    ) {}

    public function render(Page $page): ?string
    {
        $built = $page->builder_published;

        if (! is_array($built)) {
            return null;
        }

        $version = max(1, (int) ($page->builder_version ?? 1));
        $key = "pb:doc:{$page->id}:v{$version}:p{$this->epoch()}";
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $content = $this->pageContent($page, $built);

        if (trim($content) === '') {
            return null;
        }

        $css = $this->sanitizer->sanitizeCss((string) ($built['css'] ?? ''));
        $document = $this->document($content, $css, $page);

        Cache::put($key, $document, (int) config('pagebuilder.cache_ttl_seconds'));

        return $document;
    }

    /**
     * Render the not-found template as a full document (null when the builder
     * 404 is not enabled, so the theme keeps handling errors).
     */
    public function renderNotFound(): ?string
    {
        $body = $this->templateHtml('not_found');

        if ($body === null || trim($body) === '') {
            return null;
        }

        $css = $this->templateCss('not_found');
        $title = __('pagebuilder.not_found_title').' · '.$this->siteName();

        return $this->document($body, $css, null, $title);
    }

    /**
     * Invalidate one page's cached documents (old version keys expire by TTL).
     */
    public function forgetPage(int $pageId, int $version): void
    {
        Cache::forget("pb:doc:{$pageId}:v{$version}:p{$this->epoch()}");
    }

    /**
     * Bump the global purge epoch: every cached document/template render is
     * abandoned and rebuilt on the next request. A monotonic counter (not a
     * timestamp) so two bumps in the same second still produce new keys.
     */
    public function purge(): void
    {
        if (! Cache::has(self::EPOCH_KEY)) {
            Cache::add(self::EPOCH_KEY, 0, now()->addDays(30));
        }

        Cache::increment(self::EPOCH_KEY);
    }

    private function epoch(): int
    {
        return (int) Cache::get(self::EPOCH_KEY, 0);
    }

    /**
     * @param  array<string, mixed>  $built
     */
    private function pageContent(Page $page, array $built): string
    {
        $tree = $this->tree($built);

        if ($tree !== null) {
            return $this->registry->renderTree($tree, new RenderContext($this->settings, $page));
        }

        // Legacy payload: re-sanitize on render (defense in depth against DB edits).
        return (string) $this->sanitizer->sanitizeHtml((string) ($built['html'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<ComponentNode>|null
     */
    private function tree(array $payload): ?array
    {
        $raw = is_array($payload['components'] ?? null) ? $payload['components'] : null;

        if ($raw === null || $raw === []) {
            return null;
        }

        return array_values(array_map(
            static fn (array $node): ComponentNode => ComponentNode::fromArray($node),
            array_filter($raw, 'is_array'),
        ));
    }

    private function templateHtml(string $type): ?string
    {
        $template = BuilderTemplate::query()
            ->where('type', $type)
            ->where('enabled', true)
            ->first();

        if ($template === null) {
            return null;
        }

        $key = "pb:tpl:{$type}:v{$template->version}:p{$this->epoch()}";
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        $tree = $template->tree();
        $html = $tree !== null
            ? $this->registry->renderTree($tree, new RenderContext($this->settings))
            : (string) $this->sanitizer->sanitizeHtml((string) $template->html);

        Cache::put($key, $html, (int) config('pagebuilder.cache_ttl_seconds'));

        return $html;
    }

    private function templateCss(string $type): string
    {
        $template = BuilderTemplate::query()
            ->where('type', $type)
            ->where('enabled', true)
            ->first();

        return $template === null
            ? ''
            : $this->sanitizer->sanitizeCss((string) ($template->css ?? ''));
    }

    private function siteName(): string
    {
        return (string) $this->settings->get('site_name', config('app.name'));
    }

    /**
     * Assemble the standalone document: SEO head, Bootstrap + design tokens +
     * editor CSS, template header/footer chrome when enabled, CMS menus inside.
     */
    private function document(string $content, string $css, ?Page $page, ?string $title = null): string
    {
        $siteName = $this->siteName();

        if ($title === null) {
            $title = $page === null ? $siteName : trim((string) ($page->seo_title ?: $page->title));

            if ($title !== '' && $title !== $siteName) {
                $title = $title.' · '.$siteName;
            } else {
                $title = $siteName;
            }
        }

        $description = $page === null ? '' : trim((string) ($page->seo_description ?: ''));
        $description = $description !== '' ? $description : trim((string) $this->settings->get('seo_description', ''));
        $ogImage = $page !== null ? $this->ogImage($page) : null;

        $body = '<!DOCTYPE html>'
            .'<html lang="'.$this->langAttr().'">'
            .'<head>'
            .'<meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</title>'
            .'<meta name="description" content="'.htmlspecialchars($description, ENT_QUOTES, 'UTF-8').'">'
            .'<meta property="og:title" content="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'
            .'<meta property="og:site_name" content="'.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').'">'
            .'<meta property="og:type" content="website">'
            .'<meta property="og:url" content="'.htmlspecialchars(url()->current(), ENT_QUOTES, 'UTF-8').'">';

        if ($description !== '') {
            $body .= '<meta property="og:description" content="'.htmlspecialchars($description, ENT_QUOTES, 'UTF-8').'">';
        }

        if ($ogImage !== null) {
            $body .= '<meta property="og:image" content="'.htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8').'">';
        }

        $safeCss = str_replace('</style', '<\/style', $css.$this->design->css());
        $chromeCss = '.pb-nav-link{color:#334155;font-weight:500;padding:.25rem .5rem;border-radius:.375rem}'
            .'.pb-nav-link:hover{color:#0d6efd}'
            .'.pb-footer-nav a{color:#cbd5e1;text-decoration:none}'
            .'.pb-footer-nav a:hover{color:#fff}'
            .'.pb-dropdown-menu{display:none;position:absolute;top:100%;right:0;min-width:160px;z-index:100;'
            .'padding:.35rem 0;background:#fff;border:1px solid #e2e8f0;border-radius:.375rem;box-shadow:0 6px 16px rgb(0 0 0/.15)}'
            .'.pb-dropdown-menu .dropdown-item{display:block;padding:.4rem .9rem;color:#334155;text-decoration:none;font-size:.875rem}'
            .'.pb-dropdown-menu .dropdown-item:hover{background:#f1f5f9;color:#0d6efd}'
            .'.pb-has-children:hover .pb-dropdown-menu{display:block}';

        $body .= '<link rel="stylesheet" href="'.htmlspecialchars(config('pagebuilder.bootstrap_css'), ENT_QUOTES, 'UTF-8').'">';
        $body .= '<style>'.$chromeCss.($safeCss !== '' ? $safeCss : '').'</style>';
        $body .= '</head><body>';

        $body .= $this->header($siteName);
        $body .= '<main class="pb-main">'.$content.'</main>';
        $body .= $this->footer($siteName);

        $body .= '</body></html>';

        return $body;
    }

    private function header(string $siteName): string
    {
        $template = $this->templateHtml('header');

        if ($template !== null && trim($template) !== '') {
            return $template;
        }

        return '<header class="pb-header py-3 mb-4 border-bottom bg-white">'
            .'<div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">'
            .'<a href="/" class="pb-brand h4 mb-0 text-decoration-none text-dark">'.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').'</a>'
            .'<nav class="pb-nav d-flex flex-wrap align-items-center gap-2">'.$this->navLinks('primary').'</nav>'
            .'</div></header>';
    }

    private function footer(string $siteName): string
    {
        $template = $this->templateHtml('footer');

        if ($template !== null && trim($template) !== '') {
            return $template;
        }

        $year = now()->year;

        return '<footer class="pb-footer py-4 mt-5 bg-dark text-white-50">'
            .'<div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">'
            .'<nav class="pb-footer-nav d-flex flex-wrap gap-3">'.$this->navLinks('footer').'</nav>'
            .'<p class="mb-0 small">© '.$year.' '.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').'</p>'
            .'</div></footer>';
    }

    /**
     * Root links of a menu, with second level as a CSS hover dropdown.
     */
    private function navLinks(string $location): string
    {
        $menu = Menu::query()->where('location', $location)->first();

        if ($menu === null) {
            return '';
        }

        $byParent = $menu->items()->get()->groupBy('parent_id');
        $out = '';

        foreach ($byParent->get(null, collect()) as $item) {
            $out .= $this->navItem($item, $byParent);
        }

        return $out;
    }

    /**
     * @param  Collection<int, Collection<int, MenuItem>>  $byParent
     */
    private function navItem(MenuItem $item, Collection $byParent): string
    {
        $href = $this->itemHref($item);

        if ($href === null) {
            return '';
        }

        $label = htmlspecialchars((string) $item->label, ENT_QUOTES, 'UTF-8');
        $children = $byParent->get($item->id, collect());

        $sub = '';

        if ($children->isNotEmpty()) {
            $items = '';

            foreach ($children as $child) {
                $childHref = $this->itemHref($child);

                if ($childHref !== null) {
                    $items .= '<a class="dropdown-item" href="'.htmlspecialchars($childHref, ENT_QUOTES, 'UTF-8').'">'
                        .htmlspecialchars((string) $child->label, ENT_QUOTES, 'UTF-8').'</a>';
                }
            }

            if ($items !== '') {
                $sub = '<div class="pb-dropdown-menu">'.$items.'</div>';
            }
        }

        return '<span class="pb-nav-item position-relative'.($sub !== '' ? ' pb-has-children' : '').'">'
            .'<a class="pb-nav-link text-decoration-none" href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.$label.'</a>'
            .$sub.'</span>';
    }

    /**
     * Resolve the public URL of a menu item; null hides the item.
     */
    private function itemHref(MenuItem $item): ?string
    {
        if ($item->type === 'page') {
            $page = $item->page_id ? Page::query()->whereKey($item->page_id)->first() : null;

            if ($page === null || ! $page->isPublished()) {
                return null;
            }

            return $page->is_home ? '/' : '/'.$page->slug;
        }

        $url = trim((string) $item->url);

        return $url === '' ? null : $url;
    }

    private function ogImage(Page $page): ?string
    {
        if ($page->ogImage()->exists()) {
            return url('/media/'.$page->ogImage->path);
        }

        $value = trim((string) ($this->settings->get('seo_og_image') ?? ''));

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return url('/media/'.$value);
        }

        return $value;
    }

    private function langAttr(): string
    {
        return str_replace('_', '-', app()->getLocale());
    }
}
