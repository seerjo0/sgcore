<?php

namespace App\Modules\Themes\Services;

use App\Modules\Pages\Models\Page;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Converts a bundled/imported theme's static HTML template into a GrapesJS
 * builder project so it opens as a fully editable (native-block) Page draft.
 *
 * The theme chrome (header/footer/<nav> menus) is intentionally dropped: the
 * Site Builder already emits the header/footer/menu chrome through its own
 * templates and the Menu module, so importing them would double them. Content
 * inside <main> is mapped onto the allowlisted component types; anything that
 * has no equivalent becomes a sanitized `html` (custom code) block so nothing
 * is silently lost. `data-cms-slot` markers with a matching builder binding are
 * translated to those tokens (e.g. site.name → {site:name}).
 */
class ThemePageImporter
{
    private const CHROME_TAGS = ['header', 'footer', 'nav', 'aside'];

    private const CHROME_CLASSES = ['site-header', 'site-footer', 'main-nav', 'navbar', 'nav-list'];

    /**
     * Document-head tags. loadHTML flattens the wrapped template, so these can
     * surface as body-level children; they are metadata, never page content.
     */
    private const HEAD_TAGS = ['head', 'meta', 'link', 'title', 'style', 'script', 'noscript', 'base'];

    /** data-cms-slot id → builder render-time binding. */
    private const SLOT_BINDINGS = [
        'site.name' => '{site:name}',
        'page.title' => '{page:title}',
    ];

    /** data-pb-type-aware heading detection. */
    private const HEADING_TAGS = ['h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6];

    /** Slug of the theme being converted right now (relative-asset rewriting). */
    private ?string $convertingSlug = null;

    public function __construct(private ThemeDiscovery $themes) {}

    /**
     * The template keys a theme exposes (home, page, 404).
     *
     * @return list<string>
     */
    public function templates(string $themeSlug): array
    {
        return array_keys($this->requireTheme($themeSlug)->templates());
    }

    /**
     * Build a GrapesJS project (editor-ready shape) from a theme template.
     *
     * @return array<string, mixed>
     */
    public function import(string $themeSlug, string $template): array
    {
        $theme = $this->requireTheme($themeSlug);
        $file = $theme->templatePath($template);

        if ($file === null) {
            throw new RuntimeException("Template '{$template}' não encontrado no tema '{$themeSlug}'.");
        }

        $this->convertingSlug = $theme->slug;

        try {
            return $this->project($this->walk($this->bodyOf($file)));
        } finally {
            $this->convertingSlug = null;
        }
    }

    /**
     * The theme's own stylesheet, with relative asset references rewritten to
     * /theme-assets/{slug}/... so a converted page keeps the theme's look in
     * the builder preview and on the public render (served as page CSS).
     */
    public function themeStyles(string $themeSlug): string
    {
        $theme = $this->requireTheme($themeSlug);
        $file = $theme->path.'/styles.css';

        if (! is_file($file)) {
            return '';
        }

        $this->convertingSlug = $theme->slug;

        try {
            return $this->rewriteAssetUrls((string) file_get_contents($file));
        } finally {
            $this->convertingSlug = null;
        }
    }

    /**
     * Create one draft Page per template the theme exposes (home/page/404),
     * each carrying its converted builder project + the theme stylesheet.
     *
     * @return list<Page>
     */
    public function importAll(string $themeSlug, string $titleBase): array
    {
        $theme = $this->requireTheme($themeSlug);
        $css = $this->themeStyles($themeSlug);
        $pages = [];

        foreach (array_keys($theme->templates()) as $key) {
            $pages[] = Page::query()->create([
                'title' => $key === 'home' ? $titleBase : $titleBase.' — '.$key,
                'slug' => $key === 'home'
                    ? $this->uniqueSlug($titleBase)
                    : $this->uniqueSlug($titleBase.'-'.$key),
                'status' => 'draft',
                'is_home' => false,
                'content' => ['body' => ''],
                'builder_draft' => $this->import($themeSlug, $key),
                'builder_custom_css' => $css !== '' ? $css : null,
            ]);
        }

        return $pages;
    }

    /**
     * Create a draft Page whose builder project is the converted template,
     * carrying the theme stylesheet as page CSS so the preview matches the
     * theme (the builder's own chrome/styles are Bootstrap-only).
     */
    public function importToPage(string $themeSlug, string $template, string $title): Page
    {
        $css = $this->themeStyles($themeSlug);

        $page = $this->createDraftPage(
            $title,
            $this->import($themeSlug, $template),
            $css !== '' ? $css : null,
        );

        return $page;
    }

    private function createDraftPage(string $title, array $project, ?string $css): Page
    {
        return Page::query()->create([
            'title' => $title,
            'slug' => $this->uniqueSlug($title),
            'status' => 'draft',
            'is_home' => false,
            'content' => ['body' => ''],
            'builder_draft' => $project,
            'builder_custom_css' => $css,
        ]);
    }

    /**
     * @return DOMNode|null
     */
    private function bodyOf(string $file)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><div id="pb-import-root">'.
            file_get_contents($file).'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('pb-import-root');

        return $this->firstTag($root, 'body') ?? $root;
    }

    /**
     * @return array<string, mixed>
     */
    private function project(array $nodes): array
    {
        return [
            'dataSources' => [],
            'assets' => [],
            'styles' => [],
            'pages' => [[
                'id' => 'imported-'.bin2hex(random_bytes(4)),
                'type' => 'main',
                'frames' => [[
                    'id' => 'frame-imported',
                    'component' => ['type' => 'wrapper', 'components' => array_values($nodes)],
                ]],
            ]],
            'symbols' => [],
        ];
    }

    /**
     * Walk an element's children, returning component nodes to inject in the parent.
     *
     * @return list<array<string, mixed>>
     */
    private function walk(?DOMNode $node): array
    {
        if ($node === null) {
            return [];
        }

        $out = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                foreach ($this->map($child) as $mapped) {
                    $out[] = $mapped;
                }

                continue;
            }

            if ($child instanceof \DOMText && trim($child->textContent) !== '') {
                $out[] = $this->textNode(trim($child->textContent));
            }
        }

        return $out;
    }

    /**
     * Map a single element to zero, one or many component nodes.
     *
     * @return list<array<string, mixed>>
     */
    private function map(DOMElement $el): array
    {
        if ($this->isChrome($el)) {
            return [];
        }

        $tag = strtolower($el->nodeName);

        // Flattened document-head tags are metadata, not content — drop them
        // (and any <style>/<script> the theme kept inline).
        if (in_array($tag, $this::HEAD_TAGS, true)) {
            return [];
        }

        // <main> just unwraps into its children.
        if ($tag === 'main') {
            return $this->walk($el);
        }

        if ($tag === 'section') {
            return [$this->node('section', 'section', $this->classesOf($el), $this->walk($el))];
        }

        // Recognized layout wrappers → container/row/column; other divs hoist.
        if ($tag === 'div' && ($this->hasClass($el, 'container') || $this->isLayout($el))) {
            return [$this->layoutNode($el)];
        }

        if (isset($this::HEADING_TAGS[$tag])) {
            return [$this->headingNode($el, (string) $this::HEADING_TAGS[$tag])];
        }

        if ($tag === 'p') {
            return [$this->textOrHtml($el)];
        }

        if ($tag === 'img') {
            return [$this->imageNode($el->getAttribute('src'), $el->getAttribute('alt'))];
        }

        if ($tag === 'a' && $this->hasClass($el, 'btn')) {
            return [$this->buttonNode($el)];
        }

        if (in_array($tag, ['ul', 'ol'], true)) {
            return [$this->listNode($el)];
        }

        if ($tag === 'blockquote') {
            return [$this->blockquoteNode($el)];
        }

        // Image/logo/gallery slot divs become an (empty) image so the user picks media.
        $slot = $el->getAttribute('data-cms-slot');
        if ($slot !== '' && preg_match('/(image|logo|gallery|hero)/i', $slot) === 1) {
            return [$this->imageNode('', $slot)];
        }

        // Any element with inline/rich content → editable sanitized HTML block.
        if ($this->hasElementChild($el)) {
            return $this->walk($el);
        }

        $html = trim($el->ownerDocument->saveHTML($el));

        return $html === '' ? [] : [$this->htmlNode($html)];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutNode(DOMElement $el): array
    {
        $classes = $this->classesOf($el);
        $type = 'container';

        foreach ($classes as $class) {
            $type = match (true) {
                $class === 'row' => 'row',
                str_starts_with($class, 'col') => 'column',
                default => $type,
            };
        }

        return $this->node($type, 'div', $classes, $this->walk($el));
    }

    /**
     * @return array<string, mixed>
     */
    private function headingNode(DOMElement $el, string $level): array
    {
        $content = $this->slotContent($el) ?? $this->innerHTML($el);

        return $this->node('heading', $el->nodeName, $this->classesOf($el), [
            $this->textNode($content),
        ], ['data-pb-level' => $level]);
    }

    /**
     * @return array<string, mixed>
     */
    private function textOrHtml(DOMElement $el): array
    {
        $content = $this->slotContent($el);
        $inner = $this->innerHTML($el);

        if ($content !== null || strip_tags($inner) === $inner) {
            return $this->node('text', 'p', $this->classesOf($el), [
                $this->textNode($content ?? $inner),
            ]);
        }

        return $this->htmlNode($el->ownerDocument->saveHTML($el));
    }

    /**
     * @return array<string, mixed>
     */
    private function imageNode(string $src, string $alt): array
    {
        return [
            'type' => 'image',
            'tagName' => 'img',
            'attributes' => array_filter(
                ['src' => $this->assetUrl($src), 'alt' => $alt, 'data-pb-type' => 'image'],
                fn ($v) => $v !== ''
            ),
            'classes' => ['img-fluid'],
            'components' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buttonNode(DOMElement $el): array
    {
        $label = trim($el->textContent);

        return $this->node('button', 'a', $this->classesOf($el), [
            $this->textNode($label),
        ], ['data-pb-text' => $label, 'href' => $el->getAttribute('href') ?: '#']);
    }

    /**
     * @return array<string, mixed>
     */
    private function listNode(DOMElement $el): array
    {
        $items = [];
        $children = [];

        foreach ($el->getElementsByTagName('li') as $li) {
            $text = trim($li->textContent);

            if ($text !== '') {
                $items[] = $text;
                $children[] = $this->plain('li', [$this->textNode($text)]);
            }
        }

        return $this->node('list', $el->nodeName, $this->classesOf($el), $children, [
            'data-pb-items' => implode("\n", $items),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function blockquoteNode(DOMElement $el): array
    {
        $content = $this->innerHTML($el);
        $cite = '';

        foreach ($el->getElementsByTagName('cite') as $c) {
            $cite = trim($c->textContent);
        }

        return $this->node('blockquote', 'blockquote', $this->classesOf($el), [
            $this->textNode($content),
        ], $cite !== '' ? ['data-pb-cite' => $cite] : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function htmlNode(string $html): array
    {
        // Same visible placeholder the editor's own HTML block carries, so the
        // imported block is not an invisible div in the canvas; the payload
        // lives in the `data-pb-code` trait.
        return $this->node('html', 'div', [], [
            $this->textNode('&lt;!-- Escreva seu HTML na aba Propriedades --&gt;'),
        ], ['data-pb-code' => $this->rewriteMarkupAssets($html)]);
    }

    /**
     * Root-relative or absolute references already resolve publicly; theme-
     * relative ones (assets/img/hero.jpg, ./bg.png) are rewritten to the
     * /theme-assets/{slug}/ route so the converted page keeps the theme's art.
     */
    private function assetUrl(string $reference): string
    {
        $reference = trim($reference);

        if ($reference === '' || $this->convertingSlug === null) {
            return $reference;
        }

        $probe = preg_replace('/[\x00-\x20\x7F]+/', '', $reference) ?? $reference;

        if (str_starts_with($probe, '/') || str_starts_with($probe, '#')
            || preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $probe) === 1) {
            return $reference;
        }

        $clean = preg_replace('/^\.\//', '', $probe) ?? $probe;

        return '/theme-assets/'.$this->convertingSlug.'/'.ltrim($clean, '/');
    }

    /**
     * Point src/poster references inside a custom-code block at the theme
     * asset route too (navigation hrefs are left alone).
     */
    private function rewriteMarkupAssets(string $html): string
    {
        if ($this->convertingSlug === null || trim($html) === '') {
            return $html;
        }

        return preg_replace_callback(
            '#\b(src|poster)=(["\'])([^"\']+)\2#i',
            fn (array $m): string => $m[1].'='.$m[2].$this->assetUrl($m[3]).$m[2],
            $html,
        ) ?? $html;
    }

    /**
     * Rewrite url(...) references inside a stylesheet to the theme asset
     * route (same rules as assetUrl: absolute/data/root-relative pass through).
     */
    private function rewriteAssetUrls(string $css): string
    {
        if ($this->convertingSlug === null) {
            return $css;
        }

        return preg_replace_callback(
            '/url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s]*))\s*\)/i',
            function (array $m): string {
                $value = trim(
                    ($m[1] ?? '').($m[2] ?? '').($m[3] ?? ''),
                    " \t\n\r\0\x0B\"'"
                );

                return 'url("'.$this->assetUrl($value).'")';
            },
            $css,
        ) ?? $css;
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @param  list<string>  $classes
     * @return array<string, mixed>
     */
    private function node(string $pbType, string $tagName, array $classes, array $children, array $extraAttrs = []): array
    {
        // GrapesJS resolves component models from the JSON `type` when a
        // project is loaded (isComponent only runs for HTML parsing), so nodes
        // must carry the same ids the editor registers (`pb-<type>`), except
        // image/button, which the editor intentionally leaves on the built-in
        // models — a `link`-typed anchor is how its own button block serializes.
        $type = match ($pbType) {
            'image' => null,
            'button' => 'link',
            default => 'pb-'.$pbType,
        };

        return [
            'type' => $type,
            'tagName' => $tagName,
            'attributes' => array_merge(['data-pb-type' => $pbType], $extraAttrs),
            'classes' => array_values(array_unique($classes)),
            'components' => array_values($children),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @param  list<string>  $classes
     * @return array<string, mixed>
     */
    private function plain(string $tagName, array $children, array $classes = []): array
    {
        return [
            'tagName' => $tagName,
            'attributes' => [],
            'classes' => array_values(array_unique($classes)),
            'components' => array_values($children),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textNode(string $content): array
    {
        return ['type' => 'text', 'content' => $content];
    }

    private function slotContent(DOMElement $el): ?string
    {
        $slot = $el->getAttribute('data-cms-slot');

        return $this::SLOT_BINDINGS[$slot] ?? null;
    }

    private function innerHTML(DOMElement $el): string
    {
        $out = '';

        foreach ($el->childNodes as $child) {
            $out .= $el->ownerDocument->saveHTML($child);
        }

        return trim($out);
    }

    private function hasElementChild(DOMElement $el): bool
    {
        foreach ($el->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function classesOf(DOMElement $el): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($el->getAttribute('class'))) ?: []));
    }

    private function hasClass(DOMElement $el, string $needle): bool
    {
        return in_array($needle, $this->classesOf($el), true);
    }

    private function isLayout(DOMElement $el): bool
    {
        foreach ($this->classesOf($el) as $class) {
            if ($class === 'row' || str_starts_with($class, 'col')) {
                return true;
            }
        }

        return false;
    }

    private function isChrome(DOMElement $el): bool
    {
        if (in_array(strtolower($el->nodeName), $this::CHROME_TAGS, true)) {
            return true;
        }

        foreach ($this->classesOf($el) as $class) {
            if (in_array($class, $this::CHROME_CLASSES, true)) {
                return true;
            }
        }

        return $el->getAttribute('data-cms-menu') !== '';
    }

    private function firstTag(?DOMNode $node, string $tag): ?DOMElement
    {
        if ($node === null) {
            return null;
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if (strtolower($child->nodeName) === $tag) {
                    return $child;
                }

                $nested = $this->firstTag($child, $tag);

                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'importado';
        $slug = $base;
        $i = 1;

        while (Page::query()->where('slug', $slug)->exists() || in_array($slug, Page::RESERVED_SLUGS, true)) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function requireTheme(string $themeSlug): Theme
    {
        $theme = $this->themes->find($themeSlug);

        if ($theme === null || ! $theme->valid) {
            throw new RuntimeException("Tema inválido ou inexistente: {$themeSlug}");
        }

        return $theme;
    }
}
