<?php

return [
    /*
    | Size limits (bytes) applied to the editor autosave/publish payloads.
    */
    'max_json_bytes' => (int) env('PAGEBUILDER_MAX_JSON_BYTES', 8_000_000),
    'max_html_bytes' => (int) env('PAGEBUILDER_MAX_HTML_BYTES', 3_000_000),
    'max_css_bytes' => (int) env('PAGEBUILDER_MAX_CSS_BYTES', 1_000_000),

    /*
    | Bootstrap 5 committed to public so the public renderer works without a
    | node build; loaded by the editor canvas too (as an absolute asset URL).
    */
    'bootstrap_css' => '/vendor/pagebuilder/bootstrap.min.css',

    /*
    | Version of the structured (component-tree) payload schema. Bumped when
    | the tree shape or the rendering pipeline changes; the public renderer
    | keeps reading older stored payloads (positional compatibility).
    */
    'schema_version' => 1,

    /*
    | How long a rendered page stays cached before recomputation.
    */
    'cache_ttl_seconds' => (int) env('PAGEBUILDER_CACHE_TTL_SECONDS', 3600),

    /*
    | Global builder templates: types editable with the same component engine.
    | `header`/`footer` wrap builder pages; `not_found` replaces the theme 404
    | only while enabled.
    */
    'templates' => [
        'header' => 'pagebuilder.template_header',
        'footer' => 'pagebuilder.template_footer',
        'not_found' => 'pagebuilder.template_not_found',
    ],

    /*
    | Allowlisted inline-SVG icons for the `icon` component: the component only
    | ever emits one of these fixed path definitions (24x24 viewBox) keyed by
    | name, so a user-supplied icon can never inject arbitrary SVG/markup.
    */
    'icons' => [
        'check' => 'M9 16.2l-3.5-3.5-1.4 1.4L9 19 20 8l-1.4-1.4z',
        'star' => 'M12 17.3l-6.2 3.7 1.6-7L2 9.2l7.1-.6L12 2l2.9 6.6 7.1.6-5.4 4.8 1.6 7z',
        'arrow-right' => 'M12 4l-1.4 1.4L16.2 11H4v2h12.2l-5.6 5.6L12 20l8-8z',
        'phone' => 'M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24 11 11 0 0 0 3.6.58 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.4a1 1 0 0 1 1 1 11 11 0 0 0 .58 3.6 1 1 0 0 1-.24 1z',
        'mail' => 'M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4l-8 5-8-5V6l8 5 8-5z',
        'map-pin' => 'M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z',
        'play' => 'M8 5v14l11-7z',
        'clock' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 5h-2v6l5 3 1-1.7-4-2.3z',
    ],
];
