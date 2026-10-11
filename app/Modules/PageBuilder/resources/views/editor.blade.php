@php
    $isTemplate = isset($template);
    $subjectTitle = $isTemplate ? __(config('pagebuilder.templates')[$template->type]) : $page->title;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('pagebuilder.editor_title') }} — {{ $subjectTitle }}</title>
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
        .pb-history {
            position: fixed; top: 48px; right: 16px; width: 460px; max-width: 92vw; max-height: 70vh; overflow: auto;
            z-index: 1000; background: #fff; color: #1e293b; border: 1px solid #cbd5e1;
            border-radius: 10px; box-shadow: 0 12px 32px rgb(0 0 0 / .25); padding: 12px;
        }
        .pb-history h2 { margin: 0 0 8px; font-size: 14px; }
        .pb-history-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0; border-top: 1px solid #e2e8f0; font-size: 13px; }
        .pb-history-empty { color: #64748b; font-size: 13px; }
        .pb-history-diff { max-height: 40vh; overflow: auto; margin-top: 6px; border-top: 1px solid #e2e8f0; padding-top: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; white-space: pre-wrap; }
        .pb-diff-line { padding: 1px 4px; border-radius: 3px; }
        .pb-diff-add { background: #dcfce7; color: #14532d; }
        .pb-diff-del { background: #fee2e2; color: #7f1d1d; }
        .pb-diff-same { color: #64748b; }
    </style>
</head>
<body>
    <header class="pb-topbar">
        @if ($isTemplate)
            <a href="{{ route('admin.builder.templates.index') }}" class="pb-back">&larr; {{ __('pagebuilder.templates_title') }}</a>
        @else
            <a href="{{ route('admin.paginas.edit', $page) }}" class="pb-back">&larr; {{ __('pagebuilder.back_to_pages') }}</a>
        @endif
        <h1>{{ $subjectTitle }}</h1>
        @if (! $isTemplate)
            <button type="button" id="pb-history-btn" class="pb-btn">{{ __('pagebuilder.history_title') }}</button>
        @endif
        <span id="pb-save-status" class="pb-status">{{ __('pagebuilder.status_ready') }}</span>
        <button type="button" id="pb-save-now" class="pb-btn">{{ __('pagebuilder.btn_save') }}</button>
        <button type="button" id="pb-publish" class="pb-btn pb-btn-primary">{{ __('pagebuilder.btn_publish') }}</button>
    </header>

    <div id="pb-editor"></div>

    @if (! $isTemplate)
        <div id="pb-history" class="pb-history hidden">
            <h2>{{ __('pagebuilder.history_title') }}</h2>
            <div id="pb-history-items"></div>
        </div>
    @endif

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
            'mode' => $isTemplate ? 'template' : 'page',
            'page' => $isTemplate ? null : ['id' => $page->id, 'title' => $page->title],
            'template' => $isTemplate ? ['type' => $template->type] : null,
            'draft' => $draft,
            'published' => $published,
            'bootstrapCss' => asset(config('pagebuilder.bootstrap_css')),
            'components' => $componentsMetadata,
            'designTokens' => $designTokens,
            'designCss' => $designCss,
            'endpoints' => $isTemplate ? [
                'autosave' => route('admin.builder.templates.autosave', $template->type),
                'publish' => route('admin.builder.templates.publish', $template->type),
            ] : [
                'autosave' => route('admin.paginas.builder.autosave', $page),
                'publish' => route('admin.paginas.builder.publish', $page),
                'revisions' => route('admin.paginas.builder.revisions', $page),
                'restore' => url('/admin/paginas/'.$page->id.'/construtor/revisoes').'/{id}/restaurar',
                'diff' => url('/admin/paginas/'.$page->id.'/construtor/revisoes').'/{id}/diferencas',
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
                'historyTitle' => __('pagebuilder.history_title'),
                'historyEmpty' => __('pagebuilder.history_empty'),
                'historyVersion' => __('pagebuilder.history_version'),
                'historyRestore' => __('pagebuilder.history_restore'),
                'historyRestoreConfirm' => __('pagebuilder.history_restore_confirm'),
                'historyRestored' => __('pagebuilder.history_restored'),
                'historyError' => __('pagebuilder.history_error'),
                'historyDiff' => __('pagebuilder.history_diff'),
                'historyClose' => __('pagebuilder.history_close'),
                'diffAdded' => __('pagebuilder.diff_added'),
                'diffRemoved' => __('pagebuilder.diff_removed'),
                'diffNochange' => __('pagebuilder.diff_nochange'),
                'diffTruncated' => __('pagebuilder.diff_truncated'),
                'diffError' => __('pagebuilder.diff_error'),
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
                    'blockquote' => __('pagebuilder.block_blockquote'),
                    'icon' => __('pagebuilder.block_icon'),
                    'video' => __('pagebuilder.block_video'),
                    'youtube' => __('pagebuilder.block_youtube'),
                    'sampleHeading' => __('pagebuilder.sample_heading'),
                    'sampleText' => __('pagebuilder.sample_text'),
                    'sampleButton' => __('pagebuilder.sample_button'),
                    'sampleAlert' => __('pagebuilder.sample_alert'),
                    'sampleCardTitle' => __('pagebuilder.sample_card_title'),
                    'sampleCardText' => __('pagebuilder.sample_card_text'),
                    'sampleCardBtn' => __('pagebuilder.sample_card_btn'),
                    'sampleListItem' => __('pagebuilder.sample_list_item'),
                    'sampleBannerTitle' => __('pagebuilder.sample_banner_title'),
                    'sampleBannerLead' => __('pagebuilder.sample_banner_lead'),
                    'sampleBannerBtn' => __('pagebuilder.sample_banner_btn'),
                    'sampleQuote' => __('pagebuilder.sample_quote'),
                    'sampleQuoteCite' => __('pagebuilder.sample_quote_cite'),
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