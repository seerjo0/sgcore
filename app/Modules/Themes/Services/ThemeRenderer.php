<?php

namespace App\Modules\Themes\Services;

use App\Modules\Core\Services\Settings;
use App\Modules\Media\Models\Gallery;
use App\Modules\Media\Models\Medium;
use App\Modules\Pages\Models\Menu;
use App\Modules\Pages\Models\MenuItem;
use App\Modules\Pages\Models\Page;
use App\Modules\Pages\Services\ContentSanitizer;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Renders a theme template with the site's content injected:
 *
 * - [data-cms-slot="…"]  slot values (ContentStore with documented fallbacks)
 * - [data-cms-menu="…"]  menu trees built from the Menu module
 * - <link rel="stylesheet"> hrefs rewritten to /theme-assets/{theme}/…
 * - <head> SEO (title, description, og:*, favicon) rebuilt from page + settings
 *   — settings provide the defaults (site_name, site_logo, site_favicon,
 *   seo_title, seo_description, seo_og_image) and page/theme values override them
 */
class ThemeRenderer
{
    /**
     * Declaring the charset with http-equiv is what makes libxml both decode
     * AND serialize UTF-8 as raw characters (a plain <meta charset> or the
     * <?xml encoding?> trick still entity-encodes accents on saveHTML()).
     */
    private const CHARSET_PREFIX = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';

    public function __construct(
        private ThemeDiscovery $themes,
        private ContentStore $contents,
        private Settings $settings,
        private ContentSanitizer $sanitizer,
    ) {}

    /**
     * Render a template of the active theme as an HTML response.
     *
     * @param  string  $template  template key: home | page | 404
     */
    public function render(?Page $page, string $template = 'page', int $status = 200): Response
    {
        $theme = $this->themes->active();

        if ($theme === null) {
            return $this->html(
                '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>sgcore</title></head>'
                .'<body><p>Nenhum tema válido encontrado. Importe ou ative um tema em Aparência.</p></body></html>',
                $status === 200 ? 500 : $status,
            );
        }

        $templatePath = $theme->templatePath($template) ?? $theme->templatePath('home');

        if ($templatePath === null) {
            return $this->html(
                '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>sgcore</title></head>'
                .'<body><p>O tema "'.htmlspecialchars($theme->slug, ENT_QUOTES, 'UTF-8').'" não possui templates válidos.</p></body></html>',
                $status === 200 ? 500 : $status,
            );
        }

        $raw = file_get_contents($templatePath);

        if ($raw === false) {
            return $this->html('<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"></head><body><p>Erro ao ler o template.</p></body></html>', 500);
        }

        $doc = $this->loadDocument($raw);

        $this->fillSlots($doc, $theme, $page);
        $this->fillMenus($doc, $theme);
        $this->rewriteStylesheets($doc, $theme);
        $this->applySeo($doc, $theme, $page, $template);

        return $this->html($doc->saveHTML() ?: '', $status);
    }

    /**
     * Parse a theme template into a DOMDocument (UTF-8 safe, no libxml noise).
     */
    public function loadDocument(string $html): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            self::CHARSET_PREFIX.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($doc->getElementsByTagName('head')->length === 0) {
            $head = $doc->createElement('head');
            $doc->documentElement?->insertBefore($head, $doc->documentElement->firstChild);
        }

        return $doc;
    }

    /**
     * Replace every [data-cms-slot] element with its stored/default value.
     */
    private function fillSlots(DOMDocument $doc, Theme $theme, ?Page $page): void
    {
        $specs = $theme->slots();
        $pageId = $page?->id ?? 0;

        foreach ($this->elementsBy($doc, 'data-cms-slot') as $element) {
            $id = trim($element->getAttribute('data-cms-slot'));
            $element->removeAttribute('data-cms-slot');

            if ($id === '') {
                continue;
            }

            $spec = is_array($specs[$id] ?? null) ? $specs[$id] : [];
            $scope = ($spec['scope'] ?? 'page') === 'global' ? 'global' : 'page';
            $type = is_string($spec['type'] ?? null) ? $spec['type'] : 'text';

            $this->applySlot($element, $type, $this->slotValue($theme, $scope, $page, $pageId, $id, $spec));
        }
    }

    /**
     * Resolve the value of a slot: stored value wins, then built-in fallbacks
     * (site.name / page.title / page.body), then the manifest default.
     */
    private function slotValue(Theme $theme, string $scope, ?Page $page, int $pageId, string $id, array $spec): mixed
    {
        $stored = $scope === 'global'
            ? $this->contents->values('global', '', 0)
            : $this->contents->values('theme', $theme->slug, $pageId);

        if (array_key_exists($id, $stored) && $stored[$id] !== null && $stored[$id] !== '') {
            return $stored[$id];
        }

        return match ($id) {
            'site.name' => (string) $this->settings->get('site_name', config('app.name')),
            'site.logo' => (string) ($this->settings->get('site_logo') ?? ''),
            'page.title' => (string) ($page?->title ?? ''),
            'page.body' => (string) ($page?->body() ?? ''),
            default => $spec['default'] ?? '',
        };
    }

    private function applySlot(DOMElement $element, string $type, mixed $value): void
    {
        $string = is_scalar($value) ? (string) $value : '';

        switch ($type) {
            case 'richtext':
                $this->setHtml($element, (string) $this->sanitizer->sanitize($string));

                break;

            case 'image':
            case 'logo':
                // Empty value (or a media id whose record is gone) keeps the theme's placeholder.
                $url = $this->resolveMediaUrl($string);

                if ($url === '') {
                    break;
                }

                if ($element->tagName === 'img') {
                    $element->setAttribute('src', $url);
                } else {
                    $safe = str_replace(['"', "'", '\\', "\n", ')'], '', $url);
                    $style = trim($element->getAttribute('style'));
                    $element->setAttribute('style', rtrim($style, '; ').';background-image: url(\''.$safe.'\');');
                }

                break;

            case 'gallery':
                $this->fillGallery($element, $string);

                break;

            default:
                $this->setText($element, $string);

                break;
        }
    }

    /**
     * Fill [data-cms-menu="location"] with the menu tree (nested <ul><li><a>).
     * Items pointing to missing/unpublished pages are skipped.
     */
    private function fillMenus(DOMDocument $doc, Theme $theme): void
    {
        foreach ($this->elementsBy($doc, 'data-cms-menu') as $element) {
            $location = trim($element->getAttribute('data-cms-menu'));
            $element->removeAttribute('data-cms-menu');

            if (! array_key_exists($location, $theme->menus()) && ! array_key_exists($location, Menu::LOCATIONS)) {
                continue;
            }

            $this->clearChildren($element);

            $menu = Menu::query()->where('location', $location)->first();

            if ($menu === null) {
                continue;
            }

            $byParent = $menu->items()->get()->groupBy('parent_id');

            foreach ($byParent->get(null, collect()) as $item) {
                $node = $this->menuItemNode($doc, $item, $byParent, 0);

                if ($node !== null) {
                    $element->appendChild($node);
                }
            }
        }
    }

    /**
     * Build a single <li><a>…</a>[<ul>…</ul>]</li> tree node (recursive).
     *
     * @param  Collection<int, Collection<int, MenuItem>>  $byParent
     */
    private function menuItemNode(DOMDocument $doc, MenuItem $item, $byParent, int $depth): ?DOMNode
    {
        $href = $this->itemHref($item);

        if ($href === null || $depth > 5) {
            return null;
        }

        $link = $doc->createElement('a');
        $link->setAttribute('href', $href);
        $link->appendChild($doc->createTextNode((string) $item->label));

        $listItem = $doc->createElement('li');
        $listItem->appendChild($link);

        $children = $byParent->get($item->id, collect());

        if ($children !== null && $children->isNotEmpty()) {
            $nested = $doc->createElement('ul');

            foreach ($children as $child) {
                $childNode = $this->menuItemNode($doc, $child, $byParent, $depth + 1);

                if ($childNode !== null) {
                    $nested->appendChild($childNode);
                }
            }

            if ($nested->hasChildNodes()) {
                $listItem->appendChild($nested);
            }
        }

        return $listItem;
    }

    /**
     * Resolve the public URL of a menu item; null hides the item.
     */
    private function itemHref(MenuItem $item): ?string
    {
        if ($item->type === 'page') {
            $page = $item->page_id ? Page::query()->find($item->page_id) : null;

            if ($page === null || ! $page->isPublished()) {
                return null;
            }

            return $page->is_home ? '/' : '/'.$page->slug;
        }

        $url = trim((string) $item->url);

        return $url === '' ? null : $url;
    }

    /**
     * Rewrite relative stylesheet hrefs to /theme-assets/{theme}/….
     */
    private function rewriteStylesheets(DOMDocument $doc, Theme $theme): void
    {
        foreach (iterator_to_array($doc->getElementsByTagName('link')) as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            if (! str_contains(strtolower($link->getAttribute('rel')), 'stylesheet')) {
                continue;
            }

            $href = $link->getAttribute('href');

            if ($href === '' || preg_match('#^(https?:)?//#i', $href) === 1) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', $href), '/');
            $relative = preg_replace('#^(\./)+#', '', $relative) ?? $relative;

            $link->setAttribute('href', '/theme-assets/'.$theme->slug.'/'.$relative);
        }
    }

    /**
     * Rebuild <head> SEO: title, meta description and og:* tags.
     * The page title uses the same effective value as the page.title slot.
     */
    private function applySeo(DOMDocument $doc, Theme $theme, ?Page $page, string $template): void
    {
        $head = $doc->getElementsByTagName('head')->item(0);

        if (! $head instanceof DOMElement) {
            return;
        }

        $siteName = (string) $this->settings->get('site_name', config('app.name'));

        $base = trim((string) ($page?->seo_title ?: ''));

        if ($base === '' && $page !== null) {
            $storedTitle = $this->contents->values('theme', $theme->slug, $page->id)['page.title'] ?? '';
            $base = trim((string) ($storedTitle !== '' && $storedTitle !== null ? $storedTitle : $page->title));
        }

        if ($base === '' && $template === 'home') {
            $base = trim((string) $this->settings->get('seo_title', ''));
        }

        if ($base === '' && $template === '404') {
            $base = 'Página não encontrada';
        }

        $title = $base !== '' && $base !== $siteName ? $base.' · '.$siteName : $siteName;

        $description = trim((string) ($page?->seo_description ?: ''));

        if ($description === '') {
            $description = trim((string) $this->settings->get('seo_description', ''));
        }

        $ogImage = null;

        if ($page !== null && $page->ogImage()->exists()) {
            $ogImage = url('/media/'.$page->ogImage->path);
        }

        if ($ogImage === null) {
            $ogImage = $this->resolveMediaUrl((string) ($this->settings->get('seo_og_image') ?? ''));

            if ($ogImage !== '') {
                $ogImage = url($ogImage);
            } else {
                $ogImage = null;
            }
        }

        $favicon = $this->resolveMediaUrl((string) ($this->settings->get('site_favicon') ?? ''));

        // Remove whatever the template declares; the CMS owns the head now.
        $stale = [];

        foreach (iterator_to_array($head->getElementsByTagName('title')) as $node) {
            $stale[] = $node;
        }

        foreach (iterator_to_array($head->getElementsByTagName('meta')) as $node) {
            $name = $node->getAttribute('name');
            $property = $node->getAttribute('property');

            if ($name === 'description' || str_starts_with($property, 'og:')) {
                $stale[] = $node;
            }
        }

        foreach ($stale as $node) {
            $node->parentNode?->removeChild($node);
        }

        $titleNode = $doc->createElement('title');
        $titleNode->appendChild($doc->createTextNode($title));
        $head->appendChild($titleNode);

        $head->appendChild($this->metaNode($doc, 'name', 'description', $description));

        $head->appendChild($this->metaNode($doc, 'property', 'og:title', $title));
        $head->appendChild($this->metaNode($doc, 'property', 'og:site_name', $siteName));
        $head->appendChild($this->metaNode($doc, 'property', 'og:type', 'website'));
        $head->appendChild($this->metaNode($doc, 'property', 'og:url', url()->current()));

        if ($description !== '') {
            $head->appendChild($this->metaNode($doc, 'property', 'og:description', $description));
        }

        if ($ogImage !== null) {
            $head->appendChild($this->metaNode($doc, 'property', 'og:image', $ogImage));
        }

        if ($favicon !== '') {
            foreach (iterator_to_array($head->getElementsByTagName('link')) as $link) {
                if ($link instanceof DOMElement && str_contains(strtolower($link->getAttribute('rel')), 'icon')) {
                    $link->parentNode?->removeChild($link);
                }
            }

            $faviconNode = $doc->createElement('link');
            $faviconNode->setAttribute('rel', 'icon');
            $faviconNode->setAttribute('href', $favicon);
            $head->appendChild($faviconNode);
        }
    }

    private function metaNode(DOMDocument $doc, string $attribute, string $key, string $content): DOMElement
    {
        $meta = $doc->createElement('meta');
        $meta->setAttribute($attribute, $key);
        $meta->setAttribute('content', $content);

        return $meta;
    }

    /**
     * Fill a gallery slot element with <figure> items (caption → <figcaption>).
     */
    private function fillGallery(DOMElement $element, string $value): void
    {
        $this->clearChildren($element);

        $galleryId = (int) $value;

        if ($galleryId <= 0) {
            return;
        }

        $gallery = Gallery::query()->with('items.media')->find($galleryId);

        if ($gallery === null) {
            return;
        }

        $doc = $element->ownerDocument;

        if ($doc === null) {
            return;
        }

        foreach ($gallery->items as $item) {
            $media = $item->media;

            if ($media === null) {
                continue;
            }

            $figure = $doc->createElement('figure');

            $img = $doc->createElement('img');
            $img->setAttribute('src', '/media/'.$media->path);
            $img->setAttribute('alt', trim((string) ($item->caption ?: $media->alt ?: '')));
            $figure->appendChild($img);

            $caption = trim((string) $item->caption);

            if ($caption !== '') {
                $figcaption = $doc->createElement('figcaption');
                $figcaption->appendChild($doc->createTextNode($caption));
                $figure->appendChild($figcaption);
            }

            $element->appendChild($figure);
        }
    }

    /**
     * Image slots store a media library id (what the admin picker writes);
     * paths and full URLs (theme defaults/legacy values) pass through.
     */
    private function resolveMediaUrl(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value)) {
            return Medium::query()->find((int) $value)?->url() ?? '';
        }

        return $this->mediaUrl($value);
    }

    /**
     * Turn a media value into a public URL: full URLs and root-relative paths
     * pass through, anything else is treated as a media library path.
     */
    private function mediaUrl(string $value): string
    {
        if (preg_match('#^(https?:)?//#i', $value) === 1 || str_starts_with($value, '/')) {
            return $value;
        }

        return '/media/'.ltrim($value, '/');
    }

    /**
     * Replace the element's children with a text node.
     */
    private function setText(DOMElement $element, string $text): void
    {
        $this->clearChildren($element);

        $doc = $element->ownerDocument;

        if ($doc !== null && $text !== '') {
            $element->appendChild($doc->createTextNode($text));
        }
    }

    /**
     * Replace the element's children with a sanitized HTML fragment.
     */
    private function setHtml(DOMElement $element, string $html): void
    {
        $this->clearChildren($element);

        if ($html === '' || $element->ownerDocument === null) {
            return;
        }

        $fragment = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $fragment->loadHTML(
            self::CHARSET_PREFIX.'<div id="cms-fragment">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($fragment);
        $root = $xpath->query('//*[@id="cms-fragment"]')?->item(0);

        if (! $root instanceof DOMNode) {
            return;
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            $element->appendChild($element->ownerDocument->importNode($child, true));
        }
    }

    private function clearChildren(DOMElement $element): void
    {
        while ($element->firstChild !== null) {
            $element->removeChild($element->firstChild);
        }
    }

    /**
     * All elements carrying the given attribute (snapshot, safe to mutate).
     *
     * @return list<DOMElement>
     */
    private function elementsBy(DOMDocument $doc, string $attribute): array
    {
        $elements = [];

        foreach (iterator_to_array($doc->getElementsByTagName('*')) as $element) {
            if ($element instanceof DOMElement && $element->hasAttribute($attribute)) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private function html(string $body, int $status): Response
    {
        return new Response($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
