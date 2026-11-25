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
                '<!DOCTYPE html><html lang="'.$this->langAttr().'"><head><meta charset="utf-8"><title>sgcore</title></head>'
                .'<body><p>'.__('site.no_theme').'</p></body></html>',
                $status === 200 ? 500 : $status,
            );
        }

        $templatePath = $theme->templatePath($template) ?? $theme->templatePath('home');

        if ($templatePath === null) {
            return $this->html(
                '<!DOCTYPE html><html lang="'.$this->langAttr().'"><head><meta charset="utf-8"><title>sgcore</title></head>'
                .'<body><p>'.__('site.theme_no_templates', ['slug' => htmlspecialchars($theme->slug, ENT_QUOTES, 'UTF-8')]).'</p></body></html>',
                $status === 200 ? 500 : $status,
            );
        }

        $raw = file_get_contents($templatePath);

        if ($raw === false) {
            return $this->html('<!DOCTYPE html><html lang="'.$this->langAttr().'"><head><meta charset="utf-8"></head><body><p>'.__('site.template_error').'</p></body></html>', 500);
        }

        $doc = $this->loadDocument($raw);

        $this->fillSlots($doc, $theme, $page);
        $this->fillMenus($doc, $theme);
        $this->rewriteStylesheets($doc, $theme);
        $this->applySeo($doc, $theme, $page, $template);

        $html = $doc->saveHTML() ?: '';

        // Gallery videos need the lightbox CSS+JS. Injecting as raw string
        // (right before </body>) keeps the style/script verbatim — routing it
        // through the DOM would escape their operators. Only when present.
        if (str_contains($html, 'class="pb-lb-video"')) {
            $snippet = $this->lightboxSnippet();

            $body = stripos($html, '</body>');

            if ($body !== false) {
                $html = substr($html, 0, $body).$snippet.substr($html, $body);
            } else {
                $html .= $snippet;
            }
        }

        return $this->html($html, $status);
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
            $base = __('site.not_found');
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

            $caption = trim((string) $item->caption);

            if ($media->isVideo()) {
                // Tile = poster frame + play icon; o play de verdade vive só
                // no lightbox (o <video controls> embutido engolia o clique
                // que deveria abrir o modal).
                $figure->setAttribute('class', 'pb-lb-video');
                $figure->setAttribute('data-lb-src', '/media/'.$media->path);
                $figure->setAttribute('data-lb-caption', $caption);
                $figure->setAttribute('role', 'button');
                $figure->setAttribute('tabindex', '0');
                $figure->setAttribute('aria-label', (string) __('media.view_video'));

                $video = $doc->createElement('video');
                $video->setAttribute('src', '/media/'.$media->path.'#t=0.5');
                $video->setAttribute('preload', 'metadata');
                $video->setAttribute('muted', 'muted');
                $video->setAttribute('playsinline', 'playsinline');
                $video->setAttribute('aria-hidden', 'true');
                $figure->appendChild($video);

                $icon = $doc->createElement('span');
                $icon->setAttribute('class', 'pb-lb-icon');
                $icon->setAttribute('aria-hidden', 'true');
                $icon->appendChild($doc->createTextNode('▶'));
                $figure->appendChild($icon);
            } else {
                $img = $doc->createElement('img');
                $img->setAttribute('src', '/media/'.$media->path);
                $img->setAttribute('alt', trim((string) ($item->caption ?: $media->alt ?: '')));
                $figure->appendChild($img);
            }

            if ($caption !== '') {
                $figcaption = $doc->createElement('figcaption');
                $figcaption->appendChild($doc->createTextNode($caption));
                $figure->appendChild($figcaption);
            }

            $element->appendChild($figure);
        }
    }

    /**
     * Self-contained lightbox (CSS+JS) injected once, only when the page
     * actually has gallery videos (.pb-lb-video). Plays media in a modal —
     * click/Esc/backdrop close, ←/→ navigate, theme-agnostic styles.
     */
    private function lightboxSnippet(): string
    {
        return <<<'HTML'
<style>.pb-lb-video{position:relative;cursor:zoom-in;overflow:hidden;border-radius:8px;background:#0b0e13}.pb-lb-video video{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;pointer-events:none}.pb-lb-video:focus-visible{outline:2px solid #b8862b;outline-offset:2px}.pb-lb-icon{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:44px;height:44px;display:grid;place-items:center;border-radius:50%;background:rgba(11,14,19,.7);color:#f4f1ea;font-size:1rem;border:1px solid rgba(216,176,74,.6);pointer-events:none}#pb-lightbox{position:fixed;inset:0;z-index:3000;display:flex;align-items:center;justify-content:center;background:rgba(8,10,14,.93);padding:clamp(12px,4vw,40px)}#pb-lightbox[hidden]{display:none}#pb-lightbox .pb-lb-stage{margin:0;display:flex;flex-direction:column;align-items:center;gap:.8rem;max-width:min(1200px,94vw)}#pb-lightbox video{display:block;max-width:min(94vw,1200px);max-height:80vh;border-radius:10px;background:#0b0e13;box-shadow:0 18px 60px rgba(0,0,0,.55)}#pb-lightbox .pb-lb-cap{color:#d8d5cc;font-size:.95rem;text-align:center}#pb-lightbox .pb-lb-cap:empty{display:none}#pb-lightbox button{position:absolute;width:44px;height:44px;border-radius:50%;border:1px solid rgba(216,176,74,.5);background:rgba(11,14,19,.55);color:#f4f1ea;cursor:pointer;font-size:1.1rem;line-height:1}#pb-lightbox button:hover{background:rgba(184,134,43,.85)}#pb-lightbox .pb-lb-x{top:12px;right:14px}#pb-lightbox .pb-lb-prev{left:clamp(8px,2vw,22px);top:50%;margin-top:-22px}#pb-lightbox .pb-lb-next{right:clamp(8px,2vw,22px);top:50%;margin-top:-22px}</style>
<script>(function(){var figs=[].slice.call(document.querySelectorAll('.pb-lb-video'));if(!figs.length){return}var box=document.createElement('div');box.id='pb-lightbox';box.setAttribute('role','dialog');box.setAttribute('aria-modal','true');box.hidden=true;box.innerHTML='<button type="button" class="pb-lb-x" aria-label="✕">✕</button><button type="button" class="pb-lb-prev" aria-label="‹">‹</button><button type="button" class="pb-lb-next" aria-label="›">›</button><figure class="pb-lb-stage"><video controls autoplay playsinline></video><figcaption class="pb-lb-cap"></figcaption></figure>';document.body.appendChild(box);var vid=box.querySelector('video'),cap=box.querySelector('.pb-lb-cap'),idx=0,last=null;function show(i){idx=i;var f=figs[i];vid.src=f.getAttribute('data-lb-src')||'';cap.textContent=f.getAttribute('data-lb-caption')||''}function open(i){last=document.activeElement;box.hidden=false;show(i);try{vid.play().catch(function(){})}catch(e){}box.querySelector('.pb-lb-x').focus()}function close(){box.hidden=true;vid.pause();vid.removeAttribute('src');vid.load();if(last&&last.focus){last.focus()}}function nav(d){show((idx+d+figs.length)%figs.length);try{vid.play().catch(function(){})}catch(e){}}figs.forEach(function(f,i){f.addEventListener('click',function(e){e.preventDefault();open(i)});f.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();open(i)}})});box.querySelector('.pb-lb-x').addEventListener('click',close);box.querySelector('.pb-lb-prev').addEventListener('click',function(){nav(-1)});box.querySelector('.pb-lb-next').addEventListener('click',function(){nav(1)});box.addEventListener('click',function(e){if(e.target===box){close()}});document.addEventListener('keydown',function(e){if(box.hidden){return}if(e.key==='Escape'){close()}else if(e.key==='ArrowLeft'){nav(-1)}else if(e.key==='ArrowRight'){nav(1)}})})();</script>
HTML;
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

    private function langAttr(): string
    {
        return str_replace('_', '-', app()->getLocale());
    }

    private function html(string $body, int $status): Response
    {
        return new Response($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
