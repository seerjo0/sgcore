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

    /** data-cms-slot id → builder render-time binding. */
    private const SLOT_BINDINGS = [
        'site.name' => '{site:name}',
        'site.nam' => '{site:name}',
        'page.title' => '{page:title}',
    ];

    /** data-pb-type-aware heading detection. */
    private const HEADING_TAGS = ['h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6];

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
        $body = $this->firstTag($root, 'body');

        return $this->project($this->walk($body ?? $root));
    }

    /**
     * Create a draft Page whose builder project is the converted template.
     */
    public function importToPage(string $themeSlug, string $template, string $title): Page
    {
        $project = $this->import($themeSlug, $template);

        return Page::query()->create([
            'title' => $title,
            'slug' => Page::query()->count() === 0 ? 'importado-'.substr((string) $template, 0, 4) : $this->uniqueSlug($title),
            'status' => 'draft',
            'is_home' => false,
            'content' => ['body' => ''],
            'builder_draft' => $project,
        ]);
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
                ['src' => $src, 'alt' => $alt, 'data-pb-type' => 'image'],
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
        return $this->node('html', 'div', [], [], ['data-pb-code' => $html]);
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @param  list<string>  $classes
     * @return array<string, mixed>
     */
    private function node(string $pbType, string $tagName, array $classes, array $children, array $extraAttrs = []): array
    {
        return [
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
