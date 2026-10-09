<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('pagebuilder.editor_title') }} — {{ $page->title }}</title>
    @vite(['resources/js/page-builder/editor.js'])
    <style>
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; background: #e2e8f0; color: #1e293b; }
        .pb-topbar {
            display: flex; align-items: center; gap: 12px; padding: 8px 14px;
            background: #0f172a; color: #e2e8f0; box-shadow: 0 1px 3px rgb(0 0 0 / .2); z-index: 10;
        }
        .pb-topbar h1 { margin: 0; font-size: 15px; font-weight: 600; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pb-back {
            color: #94a3b8; text-decoration: none; font-size: 13px; white-space: nowrap;
        }
        .pb-back:hover { color: #fff; }
        .pb-status { padding: 3px 10px; border-radius: 999px; background: #334155; color: #cbd5e1; font-size: 12px; }
        .pb-status.pb-status-saved { background: #14532d; color: #bbf7d0; }
        .pb-status.pb-status-error { background: #7f1d1d; color: #fecaca; }
        .pb-btn {
            border: 1px solid #334155; background: #1e293b; color: #e2e8f0;
            border-radius: 6px; padding: 6px 12px; font-size: 13px; cursor: pointer; white-space: nowrap;
        }
        .pb-btn:hover { background: #334155; }
        .pb-btn-primary {
            background: #2563eb; border-color: #2563eb; color: #fff; font-weight: 600;
        }
        .pb-btn-primary:hover { background: #1d4ed8; }
        #pb-editor { flex: 1; min-height: 0; }
        .hidden { display: none; }
        .pb-toast {
            position: fixed; right: 16px; bottom: 16px; z-index: 1000;
            padding: 10px 16px; border-radius: 8px; color: #fff; font-size: 13px;
            opacity: 0; transform: translateY(8px); transition: opacity .2s, transform .2s;
            box-shadow: 0 4px 12px rgb(0 0 0 / .2);
        }
        .pb-toast-show { opacity: 1; transform: translateY(0); }
        .pb-toast-success { background: #15803d; }
        .pb-toast-error { background: #b91c1c; }
    </style>
</head>
<body>
    <header class="pb-topbar">
        <a href="{{ route('admin.paginas.edit', $page) }}" class="pb-back">&larr; {{ __('pagebuilder.back_to_pages') }}</a>
        <h1>{{ $page->title }}</h1>
        <span id="pb-save-status" class="pb-status">{{ __('pagebuilder.status_ready') }}</span>
        <button type="button" id="pb-save-now" class="pb-btn">{{ __('pagebuilder.btn_save') }}</button>
        <button type="button" id="pb-publish" class="pb-btn pb-btn-primary">{{ __('pagebuilder.btn_publish') }}</button>
    </header>

    <div id="pb-editor"></div>

    <input type="hidden" id="pb-media-target" value="">
    <img id="pb-media-preview" src="" alt="" class="hidden">

    @include('media::picker', [
        'mode' => 'single',
        'targetField' => '#pb-media-target',
        'targetPreview' => '#pb-media-preview',
        'title' => __('pagebuilder.choose_image'),
    ])

    <script>
        window.pageBuilderConfig = <?php echo json_encode([
            'page' => ['id' => $page->id, 'title' => $page->title],
            'draft' => $draft,
            'published' => $published,
            'bootstrapCss' => asset(config('pagebuilder.bootstrap_css')),
            'endpoints' => [
                'autosave' => route('admin.paginas.builder.autosave', $page),
                'publish' => route('admin.paginas.builder.publish', $page),
            ],
            'strings' => [
                'statusReady' => __('pagebuilder.status_ready'),
                'statusSaving' => __('pagebuilder.status_saving'),
                'statusSaved' => __('pagebuilder.status_saved'),
                'statusUnsaved' => __('pagebuilder.status_unsaved'),
                'publishConfirm' => __('pagebuilder.publish_confirm'),
                'publishSuccess' => __('pagebuilder.publish_success'),
                'publishError' => __('pagebuilder.publish_error'),
                'mediaLibrary' => __('pagebuilder.media_library'),
                'blocks' => [
                    'categoryLayout' => __('pagebuilder.category_layout'),
                    'categoryContent' => __('pagebuilder.category_content'),
                    'categoryComponents' => __('pagebuilder.category_components'),
                    'section' => __('pagebuilder.block_section'),
                    'container' => __('pagebuilder.block_container'),
                    'row' => __('pagebuilder.block_row'),
                    'column' => __('pagebuilder.block_column'),
                    'heading' => __('pagebuilder.block_heading'),
                    'text' => __('pagebuilder.block_text'),
                    'image' => __('pagebuilder.block_image'),
                    'button' => __('pagebuilder.block_button'),
                    'card' => __('pagebuilder.block_card'),
                    'banner' => __('pagebuilder.block_banner'),
                    'spacer' => __('pagebuilder.block_spacer'),
                    'divider' => __('pagebuilder.block_divider'),
                    'list' => __('pagebuilder.block_list'),
                    'alert' => __('pagebuilder.block_alert'),
                    'badge' => __('pagebuilder.block_badge'),
                    'table' => __('pagebuilder.block_table'),
                    'sampleHeading' => __('pagebuilder.sample_heading'),
                    'sampleText' => __('pagebuilder.sample_text'),
                    'sampleButton' => __('pagebuilder.sample_button'),
                    'sampleAlert' => __('pagebuilder.sample_alert'),
                    'sampleCardTitle' => __('pagebuilder.sample_card_title'),
                    'sampleCardText' => __('pagebuilder.sample_card_text'),
                    'sampleCardBtn' => __('pagebuilder.sample_card_btn'),
                    'sampleBannerTitle' => __('pagebuilder.sample_banner_title'),
                    'sampleBannerLead' => __('pagebuilder.sample_banner_lead'),
                    'sampleBannerBtn' => __('pagebuilder.sample_banner_btn'),
                    'sampleListItem' => __('pagebuilder.sample_list_item'),
                    'sampleTableH1' => __('pagebuilder.sample_table_h1'),
                    'sampleTableH2' => __('pagebuilder.sample_table_h2'),
                    'categoryStyleGeneral' => __('pagebuilder.category_style_general'),
                    'categoryStyleTypography' => __('pagebuilder.category_style_typography'),
                    'categoryStyleDecorations' => __('pagebuilder.category_style_decorations'),
                    'categoryStyleLayout' => __('pagebuilder.category_style_layout'),
                ],
            ],
        ]); ?>;
    </script>
</body>
</html>