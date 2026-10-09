<?php

namespace App\Modules\PageBuilder\Services;

use App\Modules\Core\Services\Settings;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;
use Illuminate\Support\Collection;

/**
 * Renders a page whose layout was built in the Page Builder as a standalone
 * Bootstrap 5 document: CMS chrome (header with primary menu, footer with
 * footer menu) around the sanitized builder output, with the editor's CSS
 * injected as inline <style>. Returns null when the page has no published
 * builder output (the theme renderer wins instead).
 */
class Renderer
{
    public function __construct(
        private Settings $settings,
        private BuilderSanitizer $sanitizer,
    ) {}

    public function render(Page $page): ?string
    {
        $built = $page->builder_published;

        if (! is_array($built) || ! is_string($built['html'] ?? null) || trim($built['html']) === '') {
            return null;
        }

        // Re-sanitize on render: defense in depth against direct DB edits.
        $content = (string) $this->sanitizer->sanitizeHtml($built['html']);
        $css = $this->sanitizer->sanitizeCss((string) ($built['css'] ?? ''));

        if (trim($content) === '') {
            return null;
        }

        $siteName = (string) $this->settings->get('site_name', config('app.name'));
        $title = trim((string) ($page->seo_title ?: $page->title));

        if ($title !== '' && $title !== $siteName) {
            $title = $title.' · '.$siteName;
        } else {
            $title = $siteName;
        }

        $description = trim((string) ($page->seo_description ?: ''));
        $description = $description !== '' ? $description : trim((string) $this->settings->get('seo_description', ''));
        $ogImage = $this->ogImage($page);

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

        $safeCss = str_replace('</style', '<\/style', $css);
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
        return '<header class="pb-header py-3 mb-4 border-bottom bg-white">'
            .'<div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">'
            .'<a href="/" class="pb-brand h4 mb-0 text-decoration-none text-dark">'.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').'</a>'
            .'<nav class="pb-nav d-flex flex-wrap align-items-center gap-2">'.$this->navLinks('primary').'</nav>'
            .'</div></header>';
    }

    private function footer(string $siteName): string
    {
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
