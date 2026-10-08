<?php

namespace App\Modules\Pages\Services;

class ContentSanitizer
{
    /**
     * Tags allowed in the page body (allow-list sanitization).
     */
    private const ALLOWED_TAGS = '<p><br><strong><em><b><i><u><a><img><ul><ol><li><h2><h3><h4><blockquote><hr><code><pre>';

    /**
     * Attributes allowed per tag (everything else is dropped).
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'rel', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    /**
     * Sanitize an HTML fragment with an allow-list: unknown tags, event
     * handlers, styles and dangerous URL schemes (javascript:, data:…) are
     * removed. Returns null for null input, '' for empty input.
     */
    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '') {
            return '';
        }

        // strip_tags removes unknown tags (and comments) but keeps their text.
        $html = strip_tags($html, self::ALLOWED_TAGS);

        // Rebuild the allowed tags keeping only safe attributes.
        $html = preg_replace_callback(
            '/<\s*([a-z0-9]+)((?:\s+[^<>]*?)?)\s*(\/?)>/i',
            fn (array $match): string => $this->cleanTag($match),
            $html,
        ) ?? $html;

        return trim($html);
    }

    /**
     * @param  array<int, string>  $match
     */
    private function cleanTag(array $match): string
    {
        $tag = strtolower($match[1]);
        $attributes = $match[2] ?? '';
        $selfClosing = trim($match[3] ?? '') === '/';

        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];
        $clean = [];

        if ($allowed !== [] && preg_match_all(
            '/([a-zA-Z][a-zA-Z0-9_:-]*)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/',
            $attributes,
            $found,
            PREG_SET_ORDER,
        )) {
            foreach ($found as $attribute) {
                $name = strtolower($attribute[1]);
                $value = ($attribute[3] ?? '') !== ''
                    ? $attribute[3]
                    : (($attribute[4] ?? '') !== '' ? $attribute[4] : ($attribute[5] ?? ''));

                if (! in_array($name, $allowed, true)) {
                    continue;
                }

                if (in_array($name, ['href', 'src'], true)) {
                    $safe = $this->sanitizeUrl($value);

                    if ($safe === null) {
                        continue;
                    }

                    $value = $safe;
                } else {
                    $value = preg_replace('/[<>]/', '', $value) ?? $value;
                }

                $clean[$name] = $value;
            }
        }

        $output = '<'.$tag;

        foreach ($clean as $name => $value) {
            $output .= ' '.$name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        $output .= $selfClosing ? ' />' : '>';

        return $output;
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
}
