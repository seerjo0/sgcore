@php
    $isTemplate = isset($template);
    $subjectTitle = $isTemplate ? __(config('pagebuilder.templates')[$template->type]) : $page->title;
    $publicUrl = $isTemplate ? null : ($page->is_home ? url('/') : url('/'.ltrim((string) $page->slug, '/')));
    $isPublished = ! $isTemplate && $page->builder_published !== null;
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
            display: inline-flex; align-items: center; gap: 6px;
            border: 1px solid #334155; background: #1e293b; color: #e2e8f0;
            border-radius: 6px; padding: 6px 12px; font-size: 13px; cursor: pointer; white-space: nowrap;
        }
        .pb-btn:hover { background: #334155; }
        .pb-btn-primary {
            background: #2563eb; border-color: #2563eb; color: #fff; font-weight: 600;
        }
        .pb-btn-primary:hover { background: #1d4ed8; }
        .pb-tools { display: inline-flex; align-items: center; gap: 3px; }
        .pb-tool {
            display: inline-flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; padding: 0; border-radius: 6px; cursor: pointer;
            border: 1px solid #334155; background: #1e293b; color: #cbd5e1; transition: background .12s, color .12s;
        }
        .pb-tool:hover { background: #334155; color: #fff; }
        .pb-tool.is-active { background: #2563eb; border-color: #2563eb; color: #fff; }
        .pb-tool svg { width: 16px; height: 16px; }
        .pb-tool-sep { width: 1px; height: 22px; background: #334155; margin: 0 5px; }
        .pb-preview { position: fixed; inset: 0; z-index: 2000; display: flex; flex-direction: column; background: rgb(15 23 42 / .55); }
        .pb-preview-bar { display: flex; align-items: center; justify-content: space-between; padding: 8px 14px; background: #0f172a; color: #e2e8f0; }
        .pb-preview-bar strong { font-size: 14px; }
        .pb-preview iframe { flex: 1; width: 100%; border: 0; background: #fff; }
        .pb-modal { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgb(15 23 42 / .55); }
        .pb-css { width: min(760px, 94vw); max-height: 86vh; display: flex; flex-direction: column; overflow: hidden; background: #fff; border-radius: 10px; box-shadow: 0 20px 50px rgb(0 0 0 / .35); }
        .pb-css header { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 14px; background: #0f172a; color: #e2e8f0; }
        .pb-css header strong { font-size: 14px; }
        .pb-css header .pb-actions { display: flex; gap: 6px; }
        .pb-css textarea { flex: 1; width: 100%; min-height: 320px; padding: 14px 16px; border: 0; resize: none; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; line-height: 1.5; color: #1e293b; }
        .pb-css textarea:focus { outline: none; }
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

        /* Tool buttons: size their inline icons */
        .pb-btn svg { width: 15px; height: 15px; }

        /* Block palette: icon tiles */
        .gjs-block-category { border: none !important; }
        .gjs-block-category .gjs-title {
            background: #eef2f7; color: #0f172a; border: none;
            border-bottom: 1px solid #dbe2ea; padding: 11px 14px;
            font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
        }
        .gjs-block-category .gjs-title:hover { background: #e2e8f0; color: #0f172a; }
        .gjs-block-category .gjs-title [class*="open"] { color: #475569; }
        .gjs-sm-sector .gjs-sm-title {
            background: #eef2f7; color: #0f172a; border-bottom: 1px solid #dbe2ea;
            font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 10px 12px;
        }
        .gjs-blocks-c { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 10px 10px; }
        .gjs-block {
            flex: 1 1 calc(50% - 4px); min-height: 92px; margin: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 6px; border-radius: 10px; font-size: 11px; font-weight: 600; color: #475569;
            background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 1px 2px rgb(15 23 42 / .05);
            transition: transform .14s ease, box-shadow .14s ease, border-color .14s ease, color .14s ease; cursor: grab;
        }
        .gjs-block:hover {
            color: #1e293b; border-color: #94a3b8; transform: translateY(-2px);
            box-shadow: 0 8px 20px rgb(15 23 42 / .14);
        }
        .gjs-block:active { cursor: grabbing; transform: translateY(0); }
        .gjs-block__media { margin: 0; }
        .gjs-block__media svg { width: 30px; height: 30px; color: #2563eb; transition: color .14s ease; }
        .gjs-block:hover .gjs-block__media svg { color: #1d4ed8; }
        .gjs-block-label { line-height: 1.25; text-align: center; }
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
        <div class="pb-tools">
            <button type="button" id="pb-device-desktop" class="pb-tool" title="{{ __('pagebuilder.tool_desktop') }}" aria-label="{{ __('pagebuilder.tool_desktop') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></button>
            <button type="button" id="pb-device-tablet" class="pb-tool" title="{{ __('pagebuilder.tool_tablet') }}" aria-label="{{ __('pagebuilder.tool_tablet') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M10 18h4"/></svg></button>
            <button type="button" id="pb-device-mobile" class="pb-tool" title="{{ __('pagebuilder.tool_mobile') }}" aria-label="{{ __('pagebuilder.tool_mobile') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M10 5h4M11 18h2"/></svg></button>
            <span class="pb-tool-sep"></span>
            <button type="button" id="pb-undo" class="pb-tool" title="{{ __('pagebuilder.tool_undo') }}" aria-label="{{ __('pagebuilder.tool_undo') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a4 4 0 0 1 0 8H9"/></svg></button>
            <button type="button" id="pb-redo" class="pb-tool" title="{{ __('pagebuilder.tool_redo') }}" aria-label="{{ __('pagebuilder.tool_redo') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14 20 9l-5-5"/><path d="M20 9H9a4 4 0 0 0 0 8h6"/></svg></button>
            <span class="pb-tool-sep"></span>
            <button type="button" id="pb-duplicate" class="pb-tool" title="{{ __('pagebuilder.tool_duplicate') }}" aria-label="{{ __('pagebuilder.tool_duplicate') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg></button>
            <button type="button" id="pb-delete" class="pb-tool" title="{{ __('pagebuilder.tool_delete') }}" aria-label="{{ __('pagebuilder.tool_delete') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
        </div>
        @if (! $isTemplate)
            <button type="button" id="pb-history-btn" class="pb-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ __('pagebuilder.history_title') }}</button>
        @endif
        <span id="pb-save-status" class="pb-status">{{ __('pagebuilder.status_ready') }}</span>
        @if (! $isTemplate)
            <button type="button" id="pb-preview" class="pb-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>{{ __('pagebuilder.btn_preview') }}</button>
            @if ($isPublished)
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="pb-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>{{ __('pagebuilder.btn_view') }}</a>
            @endif
            <button type="button" id="pb-discard" class="pb-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>{{ __('pagebuilder.btn_discard') }}</button>
            <button type="button" id="pb-css-btn" class="pb-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H7a2 2 0 0 0-2 2v4a2 2 0 0 1-2 2 2 2 0 0 1 2 2v4a2 2 0 0 0 2 2h1"/><path d="M16 3h1a2 2 0 0 1 2 2v4a2 2 0 0 0 2 2 2 2 0 0 0-2 2v4a2 2 0 0 1-2 2h-1"/></svg>{{ __('pagebuilder.btn_css') }}</button>
        @endif
        <button type="button" id="pb-save-now" class="pb-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>{{ __('pagebuilder.btn_save') }}</button>
        <button type="button" id="pb-publish" class="pb-btn pb-btn-primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/></svg>{{ __('pagebuilder.btn_publish') }}</button>
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
            'customCss' => $isTemplate ? null : $page->builder_custom_css,
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
                'preview' => route('admin.paginas.builder.preview', $page),
                'discard' => route('admin.paginas.builder.discard', $page),
                'css' => route('admin.paginas.builder.css', $page),
                'revisions' => route('admin.paginas.builder.revisions', $page),
                'restore' => url('/admin/paginas/'.$page->id.'/construtor/revisoes').'/{id}/restaurar',
                'diff' => url('/admin/paginas/'.$page->id.'/construtor/revisoes').'/{id}/diferencas',
            ],
            'strings' => [
                'statusReady' => __('pagebuilder.status_ready'),
                'statusSaving' => __('pagebuilder.status_saving'),
                'statusSaved' => __('pagebuilder.status_saved'),
                'statusUnsaved' => __('pagebuilder.status_unsaved'),
                'sessionExpired' => __('pagebuilder.session_expired'),
                'networkError' => __('pagebuilder.network_error'),
                'publishConfirm' => __('pagebuilder.publish_confirm'),
                'publishSuccess' => __('pagebuilder.publish_success'),
                'publishError' => __('pagebuilder.publish_error'),
                'previewTitle' => __('pagebuilder.preview_title'),
                'previewClose' => __('pagebuilder.preview_close'),
                'discardConfirm' => __('pagebuilder.discard_confirm'),
                'discardDone' => __('pagebuilder.discard_done'),
                'cssTitle' => __('pagebuilder.css_title'),
                'cssSaved' => __('pagebuilder.css_saved'),
                'cssClose' => __('pagebuilder.css_close'),
                'cssSave' => __('pagebuilder.btn_save'),
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
                    'categoryBlocks' => __('pagebuilder.category_blocks'),
                    'heroSplit' => __('pagebuilder.block_hero_split'),
                    'heroCenter' => __('pagebuilder.block_hero_center'),
                    'features' => __('pagebuilder.block_features'),
                    'cta' => __('pagebuilder.block_cta'),
                    'testimonial' => __('pagebuilder.block_testimonial'),
                    'pricing' => __('pagebuilder.block_pricing'),
                    'gallery' => __('pagebuilder.block_gallery'),
                    'stats' => __('pagebuilder.block_stats'),
                    'team' => __('pagebuilder.block_team'),
                    'footer' => __('pagebuilder.block_footer'),
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
                    'html' => __('pagebuilder.block_html'),
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
                    'sampleHeroTitle' => __('pagebuilder.sample_hero_title'),
                    'sampleHeroLead' => __('pagebuilder.sample_hero_lead'),
                    'sampleFeatureTitle' => __('pagebuilder.sample_feature_title'),
                    'sampleFeatureText' => __('pagebuilder.sample_feature_text'),
                    'sampleCtaTitle' => __('pagebuilder.sample_cta_title'),
                    'sampleCtaText' => __('pagebuilder.sample_cta_text'),
                    'samplePricePlan' => __('pagebuilder.sample_price_plan'),
                    'samplePriceItem' => __('pagebuilder.sample_price_item'),
                    'samplePriceCta' => __('pagebuilder.sample_price_cta'),
                    'sampleStatValue' => __('pagebuilder.sample_stat_value'),
                    'sampleStatLabel' => __('pagebuilder.sample_stat_label'),
                    'sampleTeamName' => __('pagebuilder.sample_team_name'),
                    'sampleTeamRole' => __('pagebuilder.sample_team_role'),
                    'sampleFooterAbout' => __('pagebuilder.sample_footer_about'),
                    'sampleFooterLink' => __('pagebuilder.sample_footer_link'),
                    'sampleFooterCopyright' => __('pagebuilder.sample_footer_copyright'),
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