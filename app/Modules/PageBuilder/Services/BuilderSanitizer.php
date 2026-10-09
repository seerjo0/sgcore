<?php

namespace App\Modules\PageBuilder\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitizer for the Page Builder output.
 *
 * The builder may produce any Bootstrap 5 markup (layout, tables, forms and
 * their attributes) plus data-* hooks used by Bootstrap JS. Everything else —
 * scripts, event handlers, unknown tags, dangerous URL schemes and CSS
 * exploits (@import, expression(), javascript:) — is removed on publish and
 * again on public render (defense in depth against direct DB edits).
 */
class BuilderSanitizer
{
    /**
     * Declaring the charset with http-equiv is what makes libxml both decode
     * AND serialize UTF-8 as raw characters.
     */
    private const CHARSET_PREFIX = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';

    /**
     * Tags kept by the content sanitizer (never executed, unlike script/iframe).
     *
     * @var array<string, true>
     */
    private const ALLOWED_TAGS = [
        'a' => true, 'abbr' => true, 'address' => true, 'article' => true, 'aside' => true,
        'audio' => true, 'b' => true, 'blockquote' => true, 'br' => true,
        'button' => true, 'caption' => true, 'circle' => true, 'cite' => true,
        'code' => true, 'col' => true, 'colgroup' => true, 'dd' => true,
        'defs' => true, 'del' => true, 'details' => true, 'div' => true,
        'dl' => true, 'dt' => true, 'em' => true, 'fieldset' => true,
        'figcaption' => true, 'figure' => true, 'footer' => true, 'form' => true,
        'g' => true, 'h1' => true, 'h2' => true, 'h3' => true, 'h4' => true,
        'h5' => true, 'h6' => true, 'header' => true, 'hr' => true, 'i' => true,
        'img' => true, 'input' => true, 'kbd' => true, 'label' => true,
        'legend' => true, 'li' => true, 'line' => true, 'main' => true,
        'mark' => true, 'nav' => true, 'ol' => true, 'option' => true,
        'p' => true, 'path' => true, 'picture' => true, 'polygon' => true,
        'polyline' => true, 'pre' => true, 'progress' => true, 'rect' => true,
        's' => true, 'samp' => true, 'section' => true, 'select' => true,
        'small' => true, 'source' => true, 'span' => true, 'strong' => true,
        'sub' => true, 'summary' => true, 'sup' => true, 'svg' => true,
        'table' => true, 'tbody' => true, 'td' => true, 'textarea' => true,
        'tfoot' => true, 'th' => true, 'thead' => true, 'time' => true,
        'tr' => true, 'track' => true, 'u' => true, 'ul' => true, 'var' => true,
        'video' => true,
    ];

    /**
     * Blanket-allowed attributes (class/style are further constrained, on*
     * handlers and data:-style URL vectors always die first).
     *
     * @var array<string, true>
     */
    private const ALWAYS_ALLOWED_ATTRS = [
        // global
        'class' => true, 'id' => true, 'title' => true, 'dir' => true,
        'lang' => true, 'role' => true, 'tabindex' => true, 'hidden' => true,
        'style' => true, 'contenteditable' => true,
        'aria-hidden' => true, 'aria-label' => true, 'aria-labelledby' => true,
        'aria-expanded' => true, 'aria-controls' => true, 'aria-selected' => true,
        'aria-checked' => true, 'aria-live' => true, 'aria-describedby' => true,
        // url carriers (validated separately)
        'href' => true, 'src' => true, 'poster' => true, 'action' => true,
        // media
        'alt' => true, 'width' => true, 'height' => true, 'loading' => true,
        'media' => true, 'type' => true,
        // forms
        'name' => true, 'value' => true, 'placeholder' => true, 'checked' => true,
        'disabled' => true, 'required' => true, 'selected' => true, 'multiple' => true,
        'readonly' => true, 'min' => true, 'max' => true, 'step' => true,
        'pattern' => true, 'minlength' => true, 'maxlength' => true, 'rows' => true,
        'cols' => true, 'size' => true, 'for' => true, 'accept' => true,
        'autocomplete' => true, 'autofocus' => true, 'method' => true,
        'enctype' => true, 'novalidate' => true, 'target' => true, 'rel' => true,
        'list' => true, 'inputmode' => true,
        // tables / lists
        'colspan' => true, 'rowspan' => true, 'scope' => true,
        'start' => true, 'reversed' => true,
        // misc
        'cite' => true, 'datetime' => true, 'controls' => true, 'loop' => true,
        'muted' => true, 'preload' => true,
        // inline svg presentation (no handlers, no url() attributes)
        'viewbox' => true, 'd' => true, 'points' => true, 'fill' => true,
        'stroke' => true, 'stroke-width' => true, 'cx' => true, 'cy' => true,
        'r' => true, 'rx' => true, 'ry' => true, 'x' => true, 'y' => true,
        'transform' => true, 'xmlns' => true, 'preserveaspectratio' => true,
    ];

    /**
     * URL-carrying attributes whose scheme must be validated.
     *
     * @var array<string, true>
     */
    private const URL_ATTRS = [
        'href' => true, 'src' => true, 'poster' => true, 'action' => true,
    ];

    public function sanitizeHtml(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            self::CHARSET_PREFIX.'<div id="pb-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $this->nodeById($doc, 'pb-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->sanitizeTree($root);
        $this->removeComments($root);

        $out = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Strip unsafe CSS: comments, @import/@charset, expression()/behavior,
     * javascript: URLs and url() values with a forbidden scheme.
     */
    public function sanitizeCss(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
        $css = preg_replace('/@import\b[^;]*;/i', '', $css) ?? $css;
        $css = preg_replace('/@charset\b[^;]*;/i', '', $css) ?? $css;
        $css = preg_replace('/@namespace\b[^;]*;/i', '', $css) ?? $css;

        $css = preg_replace_callback(
            '/url\s*\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)]*))\)/i',
            fn (array $match): string => $this->cleanCssUrl($match),
            $css,
        ) ?? $css;

        $css = preg_replace('/\b(?:expression|javascript|vbscript)\s*\(/i', 'none(', $css) ?? $css;
        $css = preg_replace('/\bjavascript\s*:/i', 'none', $css) ?? $css;
        $css = preg_replace('/\bbehavior\s*:/i', '', $css) ?? $css;
        $css = preg_replace('/<!--|-->/', '', $css) ?? $css;

        return trim($css);
    }

    /**
     * Recursively drop unknown tags and strip unsafe attributes.
     */
    private function sanitizeTree(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->sanitizeTree($child);

                if (! isset(self::ALLOWED_TAGS[strtolower($child->tagName)])) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                $this->sanitizeAttributes($child);
            }
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $names = [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $names[] = $attribute->name;
        }

        foreach ($names as $name) {
            $lower = strtolower($name);

            if (! $this->attributeAllowed($lower)) {
                $element->removeAttribute($name);

                continue;
            }

            $value = $element->getAttribute($name);

            if (isset(self::URL_ATTRS[$lower])) {
                $safe = $this->sanitizeUrl($value);

                if ($safe === null) {
                    $element->removeAttribute($name);
                } else {
                    $element->setAttribute($name, $safe);
                }

                continue;
            }

            if ($lower === 'style') {
                $element->setAttribute('style', $this->sanitizeCss($value));

                continue;
            }

            $element->setAttribute($name, preg_replace('/[<>]/', '', $value) ?? $value);
        }
    }

    private function attributeAllowed(string $name): bool
    {
        if (str_starts_with($name, 'on')) {
            return false;
        }

        if (str_starts_with($name, 'data-')) {
            return true;
        }

        return isset(self::ALWAYS_ALLOWED_ATTRS[$name]);
    }

    /**
     * Accept http(s), mailto, hash, root/relative URLs; reject any other
     * scheme (javascript:, data:, vbscript:…), including entity-encoded ones.
     */
    private function sanitizeUrl(string $value): ?string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $probe = preg_replace('/[\x00-\x20\x7F]+/', '', $decoded) ?? $decoded;

        if (str_starts_with($probe, '#') || str_starts_with($probe, '/') || str_starts_with($probe, '?')) {
            return $value;
        }

        if (preg_match('#^(https?://|mailto:)#i', $probe) === 1) {
            return $value;
        }

        // Any other explicit scheme is rejected; scheme-less input is relative.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $probe) === 1) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $match
     */
    private function cleanCssUrl(array $match): string
    {
        $url = trim($match[1] ?? $match[2] ?? $match[3] ?? '', " \t\n\r\0\x0B\"'");

        if ($url === '') {
            return 'none';
        }

        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $probe = preg_replace('/[\x00-\x20\x7F]+/', '', $decoded) ?? $decoded;

        if (str_starts_with($probe, '/') || str_starts_with($probe, '#') || preg_match('#^(https?:)?//#i', $probe) === 1) {
            return 'url('.$url.')';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $probe) === 1) {
            return 'none';
        }

        return 'url('.$url.')';
    }

    private function nodeById(DOMDocument $doc, string $id): ?DOMNode
    {
        foreach ($doc->getElementsByTagName('div') as $element) {
            if ($element instanceof DOMElement && $element->getAttribute('id') === $id) {
                return $element;
            }
        }

        return null;
    }

    private function removeComments(DOMNode $root): void
    {
        foreach (iterator_to_array($root->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $root->removeChild($child);
            } else {
                $this->removeComments($child);
            }
        }
    }
}
