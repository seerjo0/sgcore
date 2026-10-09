<?php

return [
    /*
    | Size limits (bytes) applied to the editor autosave/publish payloads.
    */
    'max_json_bytes' => (int) env('PAGEBUILDER_MAX_JSON_BYTES', 3_000_000),
    'max_html_bytes' => (int) env('PAGEBUILDER_MAX_HTML_BYTES', 3_000_000),
    'max_css_bytes' => (int) env('PAGEBUILDER_MAX_CSS_BYTES', 1_000_000),

    /*
    | Bootstrap 5 committed to public so the public renderer works without a
    | node build; loaded by the editor canvas too (as an absolute asset URL).
    */
    'bootstrap_css' => '/vendor/pagebuilder/bootstrap.min.css',
];
