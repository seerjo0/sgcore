import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';

(function () {
    const cfg = window.pageBuilderConfig;

    if (!cfg) {
        return;
    }

    Object.keys(cfg.endpoints ?? {}).forEach((key) => {
        const endpoint = cfg.endpoints[key];

        if (typeof endpoint !== 'string') {
            return;
        }

        const path = endpoint.replace(/^[a-z]+:\/\/[^/]+/i, '');

        cfg.endpoints[key] = path === '' ? '/' : path;
    });

    const S = cfg.strings;
    const B = S.blocks;
    const NATIVE_ATTRS = ['src', 'alt', 'title', 'href', 'width', 'height', 'type', 'name', 'value', 'placeholder'];

    const mediaIcon = '<svg viewBox="0 0 24 24" width="34" height="34"><path fill="currentColor" d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>';
    const blockIcon = '<svg viewBox="0 0 24 24" width="34" height="34"><path fill="currentColor" d="M3 5h18v14H3V5zm2 2v10h14V7H5z"/></svg>';

    const ic = (inner) => '<svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor"'
        + ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>';

    // One icon per block, keyed by the block id. Layout/content/component glyphs
    // are line-style so they read clearly at 32px on the drag palette.
    const BLOCK_ICONS = {
        // Presets
        'pb-hero-split': ic('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M12 4v16"/><rect x="14" y="8" width="4" height="4" rx="1"/><path d="M6 8h4M6 12h4M6 16h3"/>'),
        'pb-hero-center': ic('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 9h8M6 13h12M9 17h6"/>'),
        'pb-features': ic('<rect x="3" y="5" width="7" height="7" rx="1.5"/><rect x="14" y="5" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="5" rx="1.5"/><rect x="14" y="14" width="7" height="5" rx="1.5"/>'),
        'pb-cta': ic('<path d="M4 10v4l11 4V6z"/><path d="M15 8a3 3 0 0 1 0 8"/><path d="M4 14h3v4"/>'),
        'pb-testimonial': ic('<path d="M4 5h16v11H9l-4 3v-3H4z"/><path d="M8 9c1-1 2-1 2 0s-1 2 0 2M13 9c1-1 2-1 2 0s-1 2 0 2"/>'),
        'pb-pricing': ic('<path d="M3 12V5a2 2 0 0 1 2-2h7l9 9-9 9z"/><circle cx="8" cy="8" r="1.3"/>'),
        'pb-gallery': ic('<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>'),
        'pb-stats': ic('<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'),
        'pb-team': ic('<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3 3-5 6-5s6 2 6 5"/><circle cx="17" cy="9" r="2.3"/><path d="M16 15c3 0 5 1.6 5 4"/>'),
        'pb-footer': ic('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 15h18"/><path d="M6 18h6M15 18h3"/>'),
        // Layout
        'pb-section': ic('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>'),
        'pb-container': ic('<rect x="7" y="4" width="10" height="16" rx="1.5"/><path d="M4 4v16M20 4v16"/>'),
        'pb-row': ic('<rect x="3" y="6" width="8" height="12" rx="1.5"/><rect x="13" y="6" width="8" height="12" rx="1.5"/>'),
        'pb-column': ic('<rect x="8" y="4" width="8" height="16" rx="1.5"/>'),
        'pb-spacer': ic('<path d="M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4"/>'),
        'pb-divider': ic('<path d="M4 12h16"/><path d="M7 8h10M7 16h10" opacity=".4"/>'),
        'pb-banner': ic('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 10h8M8 14h5"/>'),
        // Content
        'pb-heading': ic('<path d="M6 5v14M18 5v14M6 12h12"/>'),
        'pb-text': ic('<path d="M5 7h14M5 11h14M5 15h14M5 19h9"/>'),
        'pb-image': ic('<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="M4 17l4-4 4 4 3-3 5 5"/>'),
        'pb-button': ic('<rect x="3" y="9" width="18" height="7" rx="3.5"/><path d="M8 12.5h8"/>'),
        'pb-list': ic('<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="5" cy="6" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="5" cy="18" r="1"/>'),
        'pb-video': ic('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M10 9l5 3-5 3z"/>'),
        'pb-youtube': ic('<rect x="2" y="6" width="20" height="12" rx="3"/><path d="M10 9.5l5 2.5-5 2.5z"/>'),
        'pb-blockquote': ic('<path d="M9 7H5v5h4c0 3-2 4-4 4M19 7h-4v5h4c0 3-2 4-4 4"/>'),
        // Components
        'pb-card': ic('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M7 13h7M7 16h5"/>'),
        'pb-alert': ic('<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>'),
        'pb-badge': ic('<circle cx="12" cy="12" r="8"/><path d="M12 8v4M12 15h.01"/>'),
        'pb-icon': ic('<path d="M12 3l2.2 5.6L20 11l-5.8 2.4L12 19l-2.2-5.6L4 11l5.8-2.4z"/>'),
        'pb-html': ic('<path d="m8 8-4 4 4 4"/><path d="m16 8 4 4-4 4"/><path d="M13 6 11 18"/>'),
    };

    const editor = grapesjs.init({
        container: '#pb-editor',
        height: '100%',
        width: 'auto',
        fromElement: false,
        storageManager: false,
        noticeOnUnload: true,
        assetManager: {
            storageType: 'none',
        },
        deviceManager: {
            devices: buildDevices(),
        },
        canvas: {
            styles: [cfg.bootstrapCss],
        },
        styleManager: buildStyleManager(),
        blockManager: {
            blocks: buildBlocks(),
        },
    });

    editor.DomComponents.addType('pb-image', {
        isComponent: (el) => el.tagName === 'IMG' && el.classList.contains('pb-media-image'),
        model: {
            defaults: {
                tagName: 'img',
                droppable: false,
                traits: [
                    'src',
                    'alt',
                    'title',
                    { type: 'media-picker-btn', name: 'mediaPicker' },
                ],
            },
        },
    });

    registerComponentTypes();
    syncContentProps();

    editor.Traits.addType('media-picker-btn', {
        createInput({ component }) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'gjs-btn-prim';
            button.textContent = S.mediaLibrary;
            button.addEventListener('click', () => {
                window.pbApplyMediaSelection = () => {
                    const preview = document.getElementById('pb-media-preview');
                    const src = preview && preview.src ? preview.src : '';

                    if (src) {
                        component.setAttributes({ src });
                    }

                    window.pbApplyMediaSelection = null;
                };

                window.openMediaPicker();
            });

            return button;
        },
    });

    function mediaGrid() {
        return mediaOverlay ? mediaOverlay.querySelector('[data-act="grid"]') : null;
    }

    function markSelectedTile() {
        if (! mediaOverlay || ! mediaSelected) {
            return;
        }

        mediaOverlay.querySelectorAll('.pb-media-tile').forEach((tile) => {
            tile.classList.toggle('is-active', tile.dataset.id === String(mediaSelected.id));
        });
    }

    function selectMedia(tile) {
        mediaSelected = { id: tile.dataset.id, url: tile.dataset.url, alt: tile.dataset.alt };
        markSelectedTile();
    }

    async function loadMedia(query) {
        const grid = mediaGrid();

        if (! grid) {
            return;
        }

        grid.innerHTML = '<p class="pb-media-msg">' + escapeHtml(S.mediaLoading) + '</p>';

        try {
            const response = await fetch(cfg.endpoints.mediaSearch + '?per_page=24&q=' + encodeURIComponent(query), {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();
            const items = payload.data ?? [];

            if (items.length === 0) {
                grid.innerHTML = '<p class="pb-media-msg">' + escapeHtml(S.mediaEmpty) + '</p>';

                return;
            }

            grid.innerHTML = items.map((item) => '<button type="button" class="pb-media-tile" data-id="' + item.id
                + '" data-url="' + escapeHtml(item.url) + '" data-alt="' + escapeHtml(item.alt || item.filename) + '">'
                + '<img src="' + escapeHtml(item.url) + '" alt="" loading="lazy">'
                + '<span>' + escapeHtml(item.filename) + '</span></button>').join('');

            grid.querySelectorAll('.pb-media-tile').forEach((tile) => {
                tile.addEventListener('click', () => selectMedia(tile));
            });

            markSelectedTile();
        } catch (error) {
            grid.innerHTML = '<p class="pb-media-msg">' + escapeHtml(S.mediaError) + '</p>';
        }
    }

    function openMediaModal() {
        if (mediaOverlay) {
            loadMedia(mediaOverlay.querySelector('[data-act="q"]').value);

            return;
        }

        mediaSelected = null;
        mediaOverlay = document.createElement('div');
        mediaOverlay.className = 'pb-modal';
        mediaOverlay.innerHTML = '<div class="pb-media"><header><strong>' + escapeHtml(S.mediaTitle) + '</strong>'
            + '<button type="button" class="pb-btn" data-act="close">' + escapeHtml(S.mediaClose) + '</button></header>'
            + '<div class="pb-media-search"><input type="search" data-act="q" placeholder="' + escapeHtml(S.mediaSearch) + '"></div>'
            + '<div class="pb-media-upload"><label class="pb-btn">' + escapeHtml(S.mediaUpload)
            + '<input type="file" accept="image/*" multiple hidden data-act="file"></label>'
            + '<button type="button" class="pb-btn pb-btn-primary" data-act="use">' + escapeHtml(S.mediaUse) + '</button></div>'
            + '<div class="pb-media-grid" data-act="grid"></div></div>';

        document.body.appendChild(mediaOverlay);

        let timer = null;
        mediaOverlay.querySelector('[data-act="q"]').addEventListener('input', (event) => {
            clearTimeout(timer);
            timer = setTimeout(() => loadMedia(event.target.value), 300);
        });
        mediaOverlay.querySelector('[data-act="close"]').addEventListener('click', closeMediaModal);
        mediaOverlay.querySelector('[data-act="use"]').addEventListener('click', confirmMediaSelection);
        mediaOverlay.querySelector('[data-act="file"]').addEventListener('change', (event) => uploadMedia(event.target.files));

        loadMedia('');
    }

    function confirmMediaSelection() {
        if (mediaSelected) {
            const target = document.getElementById('pb-media-target');
            const preview = document.getElementById('pb-media-preview');

            if (target) {
                target.value = mediaSelected.id;
            }

            if (preview) {
                preview.src = mediaSelected.url;
                preview.classList.remove('hidden');
            }

            if (typeof window.pbApplyMediaSelection === 'function') {
                window.pbApplyMediaSelection();
            }
        }

        closeMediaModal();
    }

    async function uploadMedia(files) {
        if (! files || files.length === 0) {
            return;
        }

        const form = new FormData();
        Array.from(files).forEach((file) => form.append('files[]', file));

        try {
            const response = await fetch(cfg.endpoints.mediaUpload, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                body: form,
            });

            if (! response.ok) {
                const body = await response.json().catch(() => null);
                toast(httpError(response, body, S.mediaError), 'error');

                return;
            }

            const payload = await response.json();
            const created = payload.data ?? [];

            if (created.length > 0) {
                mediaSelected = { id: created[0].id, url: created[0].url, alt: created[0].alt || created[0].filename };
                toast(S.mediaUploaded.replace(':count', created.length), 'success');
            }

            loadMedia(mediaOverlay.querySelector('[data-act="q"]').value);
        } catch (error) {
            toast(`${S.networkError ?? S.statusUnsaved} (${cfg.endpoints.mediaUpload})`, 'error');
        }
    }

    function closeMediaModal() {
        if (mediaOverlay) {
            mediaOverlay.remove();
            mediaOverlay = null;
            mediaSelected = null;
        }
    }

    window.openMediaPicker = openMediaModal;
    window.closeMediaPicker = closeMediaModal;

    const statusEl = document.getElementById('pb-save-status');
    const saveNowBtn = document.getElementById('pb-save-now');
    const publishBtn = document.getElementById('pb-publish');

    let loaded = false;
    let saveTimer = null;
    let saveInFlight = false;
    let dirty = false;
    let previewOverlay = null;
    let cssOverlay = null;
    let mediaOverlay = null;
    let mediaSelected = null;
    let customCss = cfg.customCss ?? '';

    editor.on('load', () => {
        if (cfg.draft && (cfg.draft.components || cfg.draft.pages)) {
            editor.loadProjectData(cfg.draft);
        }

        if (cfg.designCss) {
            const doc = editor.Canvas.getDocument();

            if (doc && doc.head) {
                doc.head.insertAdjacentHTML('beforeend', '<style data-pb-tokens>' + cfg.designCss + '</style>');
            }
        }

        loaded = true;
    });

    editor.on('update', () => {
        if (!loaded) {
            return;
        }

        dirty = true;
        setStatus(S.statusSaving);
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveDraft, 1500);
    });

    saveNowBtn.addEventListener('click', () => {
        clearTimeout(saveTimer);
        saveDraft();
    });

    publishBtn.addEventListener('click', publish);

    if (cfg.mode === 'page') {
        setupRevisions();
    }

    setupTools();

    async function saveDraft() {
        if (saveInFlight) {
            return;
        }

        clearTimeout(saveTimer);
        saveInFlight = true;

        try {
            const response = await fetch(cfg.endpoints.autosave, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    data: JSON.stringify(editor.getProjectData()),
                }),
            });

            if (response.ok) {
                dirty = false;
                setStatus(S.statusSaved);
            } else {
                setStatus(S.statusUnsaved);
                const body = await response.json().catch(() => null);
                toast(httpError(response, body, S.statusUnsaved), 'error');
            }
        } catch (error) {
            setStatus(S.statusUnsaved);
            toast(`${S.networkError ?? S.statusUnsaved} (${cfg.endpoints.autosave})`, 'error');
        } finally {
            saveInFlight = false;
        }
    }

    async function publish() {
        if (!window.confirm(S.publishConfirm)) {
            return;
        }

        setStatus(S.statusSaving);

        const project = editor.getProjectData();

        try {
            const response = await fetch(cfg.endpoints.publish, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    html: editor.getHtml(),
                    css: editor.getCss(),
                    json: JSON.stringify(project),
                }),
            });

            if (response.ok) {
                dirty = false;
                setStatus(S.statusSaved);
                toast(S.publishSuccess, 'success');
            } else {
                setStatus(S.statusUnsaved);
                const body = await response.json().catch(() => null);
                toast(httpError(response, body, S.publishError), 'error');
            }
        } catch (error) {
            setStatus(S.statusUnsaved);
            toast(`${S.networkError ?? S.statusUnsaved} (${cfg.endpoints.publish})`, 'error');
        }
    }

    async function postBuilder(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(payload ?? {}),
        });
    }

    async function openPreview() {
        const project = editor.getProjectData();
        setStatus(S.statusSaving);

        try {
            const response = await postBuilder(cfg.endpoints.preview, {
                html: editor.getHtml(),
                css: editor.getCss(),
                json: JSON.stringify(project),
            });

            if (!response.ok) {
                setStatus(S.statusUnsaved);
                const body = await response.json().catch(() => null);
                toast(httpError(response, body, S.publishError), 'error');

                return;
            }

            const data = await response.json();
            showPreview(data.html);
        } catch (error) {
            setStatus(S.statusUnsaved);
            toast(`${S.networkError ?? S.statusUnsaved} (${cfg.endpoints.preview})`, 'error');
        }
    }

    function showPreview(html) {
        if (!previewOverlay) {
            previewOverlay = document.createElement('div');
            previewOverlay.className = 'pb-preview';
            previewOverlay.innerHTML = '<div class="pb-preview-bar">'
                + '<strong>' + escapeHtml(S.previewTitle) + '</strong>'
                + '<button type="button" class="pb-btn">' + escapeHtml(S.previewClose) + '</button>'
                + '</div><iframe title="' + escapeHtml(S.previewTitle) + '"></iframe>';
            previewOverlay.querySelector('button').addEventListener('click', closePreview);
            document.body.appendChild(previewOverlay);
        }

        previewOverlay.querySelector('iframe').srcdoc = html;
    }

    function closePreview() {
        if (previewOverlay) {
            previewOverlay.remove();
            previewOverlay = null;
        }
    }

    function openCssModal() {
        if (cssOverlay) {
            return;
        }

        cssOverlay = document.createElement('div');
        cssOverlay.className = 'pb-modal';
        cssOverlay.innerHTML = '<div class="pb-css"><header><strong>' + escapeHtml(S.cssTitle) + '</strong>'
            + '<div class="pb-actions">'
            + '<button type="button" class="pb-btn pb-btn-primary" data-act="save">' + escapeHtml(S.cssSave) + '</button>'
            + '<button type="button" class="pb-btn" data-act="close">' + escapeHtml(S.cssClose) + '</button>'
            + '</div></header><textarea spellcheck="false" placeholder="seletor { propriedade: valor; }"></textarea></div>';

        const textarea = cssOverlay.querySelector('textarea');
        textarea.value = customCss;
        cssOverlay.querySelector('[data-act="save"]').addEventListener('click', () => saveCustomCss(textarea));
        cssOverlay.querySelector('[data-act="close"]').addEventListener('click', closeCssModal);
        document.body.appendChild(cssOverlay);
        textarea.focus();
    }

    async function saveCustomCss(textarea) {
        try {
            const response = await postBuilder(cfg.endpoints.css, { css: textarea.value });

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                toast(httpError(response, body, S.publishError), 'error');

                return;
            }

            customCss = textarea.value;
            toast(S.cssSaved, 'success');
            closeCssModal();
        } catch (error) {
            toast(`${S.networkError ?? S.statusUnsaved} (${cfg.endpoints.css})`, 'error');
        }
    }

    function closeCssModal() {
        if (cssOverlay) {
            cssOverlay.remove();
            cssOverlay = null;
        }
    }

    async function discardDraft() {
        if (!window.confirm(S.discardConfirm)) {
            return;
        }

        try {
            const response = await postBuilder(cfg.endpoints.discard, {});

            if (!response.ok) {
                const body = await response.json().catch(() => null);
                toast(httpError(response, body, S.publishError), 'error');

                return;
            }

            dirty = false;
            toast(S.discardDone, 'success');
            window.location.reload();
        } catch (error) {
            toast(`${S.networkError ?? S.statusUnsaved} (${cfg.endpoints.discard})`, 'error');
        }
    }

    function registerComponentTypes() {
        (cfg.components ?? []).forEach((meta) => {
            if (meta.type === 'image' || meta.type === 'button') {
                return;
            }

            const traits = [];

            Object.entries(meta.props ?? {}).forEach(([key, def]) => {
                if (key === 'content') {
                    return;
                }

                const attr = NATIVE_ATTRS.includes(key) ? key : 'data-pb-' + key;

                if (def.type === 'select') {
                    traits.push({
                        type: 'select',
                        label: def.label,
                        name: attr,
                        options: (def.options ?? []).map((v) => ({ value: v, label: v, name: v })),
                    });
                } else if (def.type === 'textarea') {
                    traits.push({ type: 'textarea', label: def.label, name: attr });
                } else {
                    traits.push({ type: 'text', label: def.label, name: attr });
                }
            });

            editor.DomComponents.addType('pb-' + meta.type, {
                isComponent: (el) => el.hasAttribute && el.getAttribute('data-pb-type') === meta.type,
                model: {
                    defaults: () => ({
                        traits,
                        ...(meta.defaults?.href !== undefined ? { href: meta.defaults.href } : {}),
                    }),
                },
            });
        });
    }

    function syncContentProps() {
        const contentTypes = ['heading', 'text', 'alert', 'badge', 'button', 'blockquote'];

        editor.on('component:update:content', (component) => {
            const attrs = component.getAttributes() ?? {};
            const type = attrs['data-pb-type'];

            if (!type || !contentTypes.includes(type)) {
                return;
            }

            const content = component.getContent() ?? '';
            const patch = {};

            if ((attrs['data-pb-content'] ?? '') !== content) {
                patch['data-pb-content'] = content;
            }

            if (type === 'button') {
                const plain = String(content).replace(/<[^>]*>/g, '');

                if ((attrs['data-pb-text'] ?? '') !== plain) {
                    patch['data-pb-text'] = plain;
                }
            }

            if (Object.keys(patch).length > 0) {
                component.addAttributes(patch);
            }
        });
    }

    function duplicateSelected() {
        const selected = editor.getSelected();
        const parent = selected && selected.parent();

        if (!parent) {
            return;
        }

        const clone = selected.clone();
        parent.components().add(clone, { at: selected.index() + 1 });
        editor.select(clone);
    }

    function setupTools() {
        const devices = { desktop: 'pb-device-desktop', tablet: 'pb-device-tablet', mobile: 'pb-device-mobile' };

        const markActive = () => {
            const current = editor.getDevice();

            Object.entries(devices).forEach(([name, id]) => {
                const btn = document.getElementById(id);

                if (btn) {
                    btn.classList.toggle('is-active', name === current);
                }
            });
        };

        Object.entries(devices).forEach(([name, id]) => {
            const btn = document.getElementById(id);

            if (btn) {
                btn.addEventListener('click', () => editor.setDevice(name));
            }
        });

        editor.on('change:device', markActive);
        markActive();

        const bind = (id, handler) => {
            const btn = document.getElementById(id);

            if (btn) {
                btn.addEventListener('click', handler);
            }
        };

        bind('pb-undo', () => editor.runCommand('core:undo'));
        bind('pb-redo', () => editor.runCommand('core:redo'));
        bind('pb-duplicate', duplicateSelected);
        bind('pb-delete', () => {
            const selected = editor.getSelected();

            if (selected) {
                selected.remove();
            }
        });
        bind('pb-preview', openPreview);
        bind('pb-discard', discardDraft);
        bind('pb-css-btn', openCssModal);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (previewOverlay) {
                    closePreview();
                }

                if (cssOverlay) {
                    closeCssModal();
                }

                if (mediaOverlay) {
                    closeMediaModal();
                }

                return;
            }

            const active = document.activeElement;

            if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable)) {
                return;
            }

            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') {
                event.preventDefault();
                duplicateSelected();
            }
        });
    }

    async function setupRevisions() {
        const btn = document.getElementById('pb-history-btn');
        const panel = document.getElementById('pb-history');
        const list = document.getElementById('pb-history-items');

        if (!btn || !panel || !list) {
            return;
        }

        btn.addEventListener('click', () => {
            panel.classList.toggle('hidden');

            if (!panel.classList.contains('hidden')) {
                loadRevisions(list);
            }
        });
    }

    async function loadRevisions(list) {
        list.innerHTML = '<div class="pb-history-empty">…</div>';

        try {
            const response = await fetch(cfg.endpoints.revisions, {
                headers: { Accept: 'application/json' },
            });
            const body = await response.json();
            const items = body?.items ?? [];

            list.innerHTML = '';

            if (items.length === 0) {
                list.innerHTML = '<div class="pb-history-empty">' + escapeHtml(S.historyEmpty) + '</div>';

                return;
            }

            items.forEach((item) => {
                const row = document.createElement('div');
                row.className = 'pb-history-item';

                const label = document.createElement('span');
                label.textContent = S.historyVersion + ' ' + item.version
                    + ' · ' + new Date(item.created_at).toLocaleString();

                const restore = document.createElement('button');
                restore.type = 'button';
                restore.className = 'gjs-btn-prim';
                restore.textContent = S.historyRestore;
                restore.addEventListener('click', () => restoreRevision(item.id, list));

                const diff = document.createElement('button');
                diff.type = 'button';
                diff.className = 'gjs-btn-prim';
                diff.style.marginRight = '6px';
                diff.textContent = S.historyDiff;
                diff.addEventListener('click', () => showDiff(item.id, item.version, list));

                row.appendChild(label);
                row.appendChild(diff);
                row.appendChild(restore);
                list.appendChild(row);
            });
        } catch (error) {
            list.innerHTML = '<div class="pb-history-empty">' + escapeHtml(S.historyError) + '</div>';
        }
    }

    async function showDiff(id, version, list) {
        let box = list.querySelector('.pb-history-diff');

        if (box && box.dataset.for === String(id)) {
            box.remove();

            return;
        }

        list.querySelectorAll('.pb-history-diff').forEach((el) => el.remove());
        box = document.createElement('div');
        box.className = 'pb-history-diff';
        box.dataset.for = String(id);
        box.textContent = S.diffLoading;
        list.insertBefore(box, list.firstChild);

        try {
            const url = String(cfg.endpoints.diff).replace('{id}', id);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                box.textContent = S.diffError;

                return;
            }

            const body = await response.json();
            const lines = body?.lines ?? [];

            box.innerHTML = '';

            if (body?.truncated) {
                const note = document.createElement('div');
                note.className = 'pb-history-empty';
                note.textContent = S.diffTruncated;
                box.appendChild(note);
            }

            if (lines.length === 0) {
                const none = document.createElement('div');
                none.className = 'pb-history-empty';
                none.textContent = S.diffNochange;
                box.appendChild(none);

                return;
            }

            lines.forEach((line) => {
                const rowLine = document.createElement('div');
                rowLine.className = 'pb-diff-line pb-diff-' + line.t;
                rowLine.textContent = (line.t === 'add' ? '+ ' : line.t === 'del' ? '- ' : '  ') + line.s;
                box.appendChild(rowLine);
            });
        } catch (error) {
            box.textContent = S.diffError;
        }
    }

    async function restoreRevision(id, list) {
        if (!window.confirm(S.historyRestoreConfirm)) {
            return;
        }

        const url = String(cfg.endpoints.restore).replace('{id}', id);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            if (response.ok) {
                toast(S.historyRestored, 'success');
                setTimeout(() => window.location.reload(), 500);
            } else {
                toast(S.historyError, 'error');
            }
        } catch (error) {
            toast(S.historyError, 'error');
        }
    }

    function buildBlocks() {
        const categoryLayout = B.categoryLayout;
        const categoryContent = B.categoryContent;
        const categoryComponents = B.categoryComponents;
        const categoryBlocks = B.categoryBlocks;

        const preset = (id, label, content) => ({
            id: 'pb-' + id,
            label,
            category: categoryBlocks,
            media: blockIcon,
            content,
            activate: true,
            select: true,
        });

        const esc = escapeHtml;

        const blocks = [
            preset('hero-split', B.heroSplit, `
                <section class="pb-section py-5" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <div class="row align-items-center g-4" data-pb-type="row">
                            <div class="col-lg-6" data-pb-type="column">
                                <h1 class="pb-heading display-5 fw-bold" data-pb-type="heading" data-pb-level="1">${esc(B.sampleHeroTitle)}</h1>
                                <p class="pb-text lead text-muted" data-pb-type="text">${esc(B.sampleHeroLead)}</p>
                                <a class="btn btn-primary btn-lg me-2" data-pb-type="button" data-pb-text="${esc(B.sampleButton)}" href="#">${esc(B.sampleButton)}</a>
                                <a class="btn btn-outline-secondary btn-lg" data-pb-type="button" data-pb-variant="outline-secondary" data-pb-text="${esc(B.sampleButton)}" href="#">${esc(B.sampleButton)}</a>
                            </div>
                            <div class="col-lg-6" data-pb-type="column">
                                <img class="img-fluid pb-media-image rounded" data-pb-type="image" src="" alt="">
                            </div>
                        </div>
                    </div>
                </section>`),
            preset('hero-center', B.heroCenter, `
                <section class="pb-section text-center py-5 bg-light" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <h1 class="pb-heading display-4 fw-bold" data-pb-type="heading" data-pb-level="1">${esc(B.sampleHeroTitle)}</h1>
                        <p class="pb-text lead text-muted" data-pb-type="text">${esc(B.sampleHeroLead)}</p>
                        <a class="btn btn-primary btn-lg" data-pb-type="button" data-pb-text="${esc(B.sampleButton)}" href="#">${esc(B.sampleButton)}</a>
                    </div>
                </section>`),
            preset('features', B.features, `
                <section class="pb-section py-5" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <h2 class="pb-heading text-center mb-4" data-pb-type="heading" data-pb-level="2">${esc(B.sampleHeading)}</h2>
                        <div class="row g-4" data-pb-type="row">
                            ${[
                                ['star', B.sampleFeatureTitle, B.sampleFeatureText],
                                ['check', B.sampleFeatureTitle, B.sampleFeatureText],
                                ['clock', B.sampleFeatureTitle, B.sampleFeatureText],
                            ].map(([icon, title, text]) => `
                            <div class="col-md-4" data-pb-type="column">
                                <span class="pb-icon d-inline-flex pb-text-primary mb-3" data-pb-type="icon" data-pb-icon="${esc(icon)}" data-pb-size="lg" data-pb-variant="primary">*</span>
                                <h3 class="pb-heading h5" data-pb-type="heading" data-pb-level="3">${esc(title)}</h3>
                                <p class="pb-text text-muted" data-pb-type="text">${esc(text)}</p>
                            </div>`).join('')}
                        </div>
                    </div>
                </section>`),
            preset('cta', B.cta, `
                <section class="pb-section text-center text-white bg-primary py-5" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <h2 class="pb-heading display-6 fw-bold" data-pb-type="heading" data-pb-level="2">${esc(B.sampleCtaTitle)}</h2>
                        <p class="pb-text lead mb-4" data-pb-type="text">${esc(B.sampleCtaText)}</p>
                        <a class="btn btn-light btn-lg" data-pb-type="button" data-pb-variant="light" data-pb-text="${esc(B.sampleButton)}" href="#">${esc(B.sampleButton)}</a>
                    </div>
                </section>`),
            preset('testimonial', B.testimonial, `
                <section class="pb-section py-5 bg-light" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <div class="row justify-content-center" data-pb-type="row">
                            <div class="col-lg-8 text-center" data-pb-type="column">
                                <span class="pb-icon d-inline-flex pb-text-primary mb-3" data-pb-type="icon" data-pb-icon="star" data-pb-size="md" data-pb-variant="primary">*</span>
                                <figure class="pb-blockquote" data-pb-type="blockquote" data-pb-cite="${esc(B.sampleQuoteCite)}">${esc(B.sampleQuote)}</figure>
                            </div>
                        </div>
                    </div>
                </section>`),
            preset('pricing', B.pricing, `
                <section class="pb-section py-5" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <h2 class="pb-heading text-center mb-4" data-pb-type="heading" data-pb-level="2">${esc(B.sampleHeading)}</h2>
                        <div class="row g-4" data-pb-type="row">
                            ${[0, 1, 2].map(() => `
                            <div class="col-md-4" data-pb-type="column">
                                <div class="card h-100" data-pb-type="card">
                                    <div class="card-body">
                                        <h3 class="pb-heading h5" data-pb-type="heading" data-pb-level="3">${esc(B.samplePricePlan)}</h3>
                                        <ul class="list-group list-group-flush pb-list" data-pb-type="list" data-pb-items="${esc(B.samplePriceItem)}">
                                            <li class="list-group-item">${esc(B.samplePriceItem)}</li>
                                        </ul>
                                        <a class="btn btn-primary w-100 mt-3" data-pb-type="button" data-pb-text="${esc(B.samplePriceCta)}" href="#">${esc(B.samplePriceCta)}</a>
                                    </div>
                                </div>
                            </div>`).join('')}
                        </div>
                    </div>
                </section>`),
            preset('gallery', B.gallery, `
                <section class="pb-section py-5" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <div class="row g-3" data-pb-type="row">
                            ${[0, 1, 2, 3, 4, 5].map(() => `
                            <div class="col-6 col-md-4" data-pb-type="column">
                                <img class="img-fluid pb-media-image rounded" data-pb-type="image" src="" alt="">
                            </div>`).join('')}
                        </div>
                    </div>
                </section>`),
            preset('stats', B.stats, `
                <section class="pb-section py-5 bg-light" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <div class="row text-center g-4" data-pb-type="row">
                            ${[0, 1, 2].map(() => `
                            <div class="col-md-4" data-pb-type="column">
                                <h3 class="pb-heading display-4 fw-bold pb-text-primary" data-pb-type="heading" data-pb-level="3">${esc(B.sampleStatValue)}</h3>
                                <p class="pb-text text-muted" data-pb-type="text">${esc(B.sampleStatLabel)}</p>
                            </div>`).join('')}
                        </div>
                    </div>
                </section>`),
            preset('team', B.team, `
                <section class="pb-section py-5" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <h2 class="pb-heading text-center mb-4" data-pb-type="heading" data-pb-level="2">${esc(B.sampleHeading)}</h2>
                        <div class="row g-4" data-pb-type="row">
                            ${[0, 1, 2].map(() => `
                            <div class="col-md-4 text-center" data-pb-type="column">
                                <img class="img-fluid pb-media-image rounded-circle mb-3" data-pb-type="image" src="" alt="">
                                <h3 class="pb-heading h5 mb-1" data-pb-type="heading" data-pb-level="3">${esc(B.sampleTeamName)}</h3>
                                <p class="pb-text text-muted" data-pb-type="text">${esc(B.sampleTeamRole)}</p>
                            </div>`).join('')}
                        </div>
                    </div>
                </section>`),
            preset('footer', B.footer, `
                <section class="pb-section py-5 bg-dark text-white" data-pb-type="section">
                    <div class="container" data-pb-type="container">
                        <div class="row g-4" data-pb-type="row">
                            <div class="col-md-5" data-pb-type="column">
                                <h3 class="pb-heading h5" data-pb-type="heading" data-pb-level="3">{site:name}</h3>
                                <p class="pb-text text-white-50" data-pb-type="text">${esc(B.sampleFooterAbout)}</p>
                            </div>
                            <div class="col-md-3" data-pb-type="column">
                                <h4 class="pb-heading h6 text-uppercase text-white-50" data-pb-type="heading" data-pb-level="4">${esc(B.sampleFooterLink)}</h4>
                                <ul class="pb-list list-unstyled" data-pb-type="list" data-pb-variant="unstyled" data-pb-items="${esc(B.sampleFooterLink)}&#10;${esc(B.sampleFooterLink)}&#10;${esc(B.sampleFooterLink)}">
                                    <li>${esc(B.sampleFooterLink)}</li>
                                    <li>${esc(B.sampleFooterLink)}</li>
                                    <li>${esc(B.sampleFooterLink)}</li>
                                </ul>
                            </div>
                            <div class="col-md-4" data-pb-type="column">
                                <h4 class="pb-heading h6 text-uppercase text-white-50" data-pb-type="heading" data-pb-level="4">{site:name}</h4>
                                <p class="pb-text text-white-50 mb-0" data-pb-type="text">${esc(B.sampleFooterAbout)}</p>
                            </div>
                        </div>
                        <hr class="border-secondary my-4">
                        <p class="pb-text text-white-50 text-center mb-0" data-pb-type="text">${esc(B.sampleFooterCopyright)}</p>
                    </div>
                </section>`),
            {
                id: 'pb-section',
                label: B.section,
                category: categoryLayout,
                media: blockIcon,
                content: '<section class="pb-section bg-light py-5" data-pb-type="section">'
                    + '<div class="container" data-pb-type="container">'
                    + '<div class="row" data-pb-type="row"><div class="col" data-pb-type="column"></div></div>'
                    + '</div></section>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-container',
                label: B.container,
                category: categoryLayout,
                media: blockIcon,
                content: '<div class="container" data-pb-type="container"></div>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-row',
                label: B.row,
                category: categoryLayout,
                media: blockIcon,
                content: '<div class="row g-3" data-pb-type="row"></div>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-column',
                label: B.column,
                category: categoryLayout,
                media: blockIcon,
                content: '<div class="col" data-pb-type="column"></div>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-spacer',
                label: B.spacer,
                category: categoryLayout,
                media: blockIcon,
                content: '<div class="pb-spacer" data-pb-type="spacer" data-pb-height="48px" style="height: 48px;"></div>',
                select: true,
            },
            {
                id: 'pb-divider',
                label: B.divider,
                category: categoryLayout,
                media: blockIcon,
                content: '<hr class="pb-divider my-4" data-pb-type="divider">',
                select: true,
            },
            {
                id: 'pb-banner',
                label: B.banner,
                category: categoryLayout,
                media: blockIcon,
                content: '<section class="pb-banner text-center text-white bg-primary py-5" data-pb-type="section">'
                    + '<div class="container" data-pb-type="container">'
                    + '<h2 class="pb-heading display-4" data-pb-type="heading">' + escapeHtml(B.sampleBannerTitle) + '</h2>'
                    + '<p class="pb-text lead mb-4" data-pb-type="text">' + escapeHtml(B.sampleBannerLead) + '</p>'
                    + '<a class="btn btn-light btn-lg" data-pb-type="button" data-pb-text="' + escapeHtml(B.sampleBannerBtn) + '" href="#">' + escapeHtml(B.sampleBannerBtn) + '</a>'
                    + '</div></section>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-heading',
                label: B.heading,
                category: categoryContent,
                media: blockIcon,
                content: '<h2 class="pb-heading display-6" data-pb-type="heading">' + escapeHtml(B.sampleHeading) + '</h2>',
                select: true,
            },
            {
                id: 'pb-text',
                label: B.text,
                category: categoryContent,
                media: blockIcon,
                content: '<p class="pb-text" data-pb-type="text">' + escapeHtml(B.sampleText) + '</p>',
                select: true,
            },
            {
                id: 'pb-image',
                label: B.image,
                category: categoryContent,
                media: mediaIcon,
                content: '<img class="img-fluid pb-media-image pb-3" data-pb-type="image" src="" alt="">',
                activate: true,
                select: true,
            },
            {
                id: 'pb-button',
                label: B.button,
                category: categoryContent,
                media: blockIcon,
                content: '<a class="btn btn-primary" data-pb-type="button" data-pb-text="' + escapeHtml(B.sampleButton) + '" href="#">' + escapeHtml(B.sampleButton) + '</a>',
                select: true,
            },
            {
                id: 'pb-list',
                label: B.list,
                category: categoryContent,
                media: blockIcon,
                content: '<ul class="list-group list-group-flush" data-pb-type="list" data-pb-items="'
                    + escapeHtml(B.sampleListItem + '\n' + B.sampleListItem) + '">'
                    + '<li class="list-group-item">' + escapeHtml(B.sampleListItem) + '</li>'
                    + '<li class="list-group-item">' + escapeHtml(B.sampleListItem) + '</li>'
                    + '</ul>',
                select: true,
            },
            {
                id: 'pb-card',
                label: B.card,
                category: categoryComponents,
                media: blockIcon,
                content: '<div class="card h-100" data-pb-type="card"><div class="card-body">'
                    + '<h5 class="pb-heading card-title" data-pb-type="heading" data-pb-level="5">' + escapeHtml(B.sampleCardTitle) + '</h5>'
                    + '<p class="pb-text card-text" data-pb-type="text">' + escapeHtml(B.sampleCardText) + '</p>'
                    + '<a class="btn btn-primary" data-pb-type="button" data-pb-text="' + escapeHtml(B.sampleCardBtn) + '" href="#">' + escapeHtml(B.sampleCardBtn) + '</a>'
                    + '</div></div>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-alert',
                label: B.alert,
                category: categoryComponents,
                media: blockIcon,
                content: '<div class="alert alert-primary pb-alert" data-pb-type="alert" role="alert">' + escapeHtml(B.sampleAlert) + '</div>',
                select: true,
            },
            {
                id: 'pb-badge',
                label: B.badge,
                category: categoryComponents,
                media: blockIcon,
                content: '<span class="badge text-bg-primary pb-badge" data-pb-type="badge">' + escapeHtml(B.sampleButton) + '</span>',
                select: true,
            },
            {
                id: 'pb-video',
                label: B.video,
                category: categoryContent,
                media: blockIcon,
                content: '<div class="pb-video" data-pb-type="video" data-pb-src="" data-pb-poster="" data-pb-aspect="16x9" style="display:flex;align-items:center;justify-content:center;min-height:200px;background:#f1f5f9;color:#94a3b8">Vídeo</div>',
                select: true,
            },
            {
                id: 'pb-youtube',
                label: B.youtube,
                category: categoryContent,
                media: blockIcon,
                content: '<div class="pb-youtube" data-pb-type="youtube" data-pb-id="" data-pb-aspect="16x9" style="display:flex;align-items:center;justify-content:center;min-height:200px;background:#f1f5f9;color:#94a3b8">YouTube</div>',
                select: true,
            },
            {
                id: 'pb-blockquote',
                label: B.blockquote,
                category: categoryContent,
                media: blockIcon,
                content: '<blockquote class="blockquote pb-blockquote" data-pb-type="blockquote" data-pb-cite="' + escapeHtml(B.sampleQuoteCite) + '">' + escapeHtml(B.sampleQuote) + '</blockquote>',
                select: true,
            },
            {
                id: 'pb-icon',
                label: B.icon,
                category: categoryComponents,
                media: blockIcon,
                content: '<span class="pb-icon d-inline-flex pb-text-primary" data-pb-type="icon" data-pb-icon="star" data-pb-size="md" data-pb-variant="primary" style="height:1.75rem;width:1.75rem">*</span>',
                select: true,
            },
            {
                id: 'pb-html',
                label: B.html,
                category: categoryComponents,
                media: blockIcon,
                content: '<div class="pb-html text-muted p-3 border border-dashed rounded" data-pb-type="html" data-pb-code="">&lt;!-- Escreva seu HTML na aba Propriedades --&gt;</div>',
                select: true,
            },
        ];

        return blocks.map((block) => ({
            ...block,
            media: BLOCK_ICONS[block.id] ?? block.media,
        }));
    }

    function buildDevices() {
        const bp = {};
        ((cfg.designTokens ?? {}).breakpoints ?? []).forEach((b) => {
            if (b && b.name && b.value) {
                bp[b.name] = b.value;
            }
        });

        return [
            { id: 'desktop', name: 'Desktop', width: '' },
            { id: 'tablet', name: 'Tablet', width: bp.md || '768px', widthMedia: bp.lg || '992px' },
            { id: 'mobile', name: 'Mobile', width: '480px', widthMedia: bp.sm || '767px' },
        ];
    }

    function buildStyleManager() {
        const opts = (list) => list.map((v) => ({ id: v, label: v }));

        return {
            sectors: [
                {
                    name: B.categoryStyleGeneral,
                    buildProps: [
                        'display', 'position', 'top', 'right', 'bottom', 'left',
                        'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height',
                        'margin', 'padding', 'float', 'clear', 'opacity', 'overflow', 'z-index',
                    ],
                },
                {
                    name: B.categoryStyleTypography,
                    buildProps: [
                        'font-family', 'font-size', 'font-weight', 'letter-spacing',
                        'line-height', 'text-align', 'text-decoration', 'text-shadow',
                        'color', 'font-style',
                    ],
                    properties: [
                        { property: 'color', type: 'color', preview: true },
                    ],
                },
                {
                    name: B.categoryStyleDecorations,
                    buildProps: [
                        'background-color', 'background-image', 'background-repeat',
                        'background-position', 'background-size', 'box-shadow',
                        'border-radius', 'border', 'border-color', 'border-style', 'border-width',
                    ],
                    properties: [
                        { property: 'background-color', type: 'color', preview: true },
                        { property: 'border-color', type: 'color' },
                    ],
                },
                {
                    name: B.categoryStyleLayout,
                    buildProps: [
                        'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'gap',
                    ],
                    properties: [
                        {
                            property: 'flex-direction',
                            type: 'select',
                            defaults: 'row',
                            options: opts(['row', 'row-reverse', 'column', 'column-reverse']),
                        },
                        {
                            property: 'flex-wrap',
                            type: 'select',
                            defaults: 'nowrap',
                            options: opts(['nowrap', 'wrap', 'wrap-reverse']),
                        },
                        {
                            property: 'justify-content',
                            type: 'select',
                            options: opts(['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly']),
                        },
                        {
                            property: 'align-items',
                            type: 'select',
                            options: opts(['stretch', 'flex-start', 'center', 'flex-end', 'baseline']),
                        },
                    ],
                },
            ],
        };
    }

    function setStatus(state) {
        statusEl.textContent = state;
        statusEl.classList.remove('pb-status-saved', 'pb-status-error');

        if (state === S.statusSaved) {
            statusEl.classList.add('pb-status-saved');
        }

        if (state === S.statusUnsaved) {
            statusEl.classList.add('pb-status-error');
        }
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.content : '';
    }

    function httpError(response, body, fallback) {
        if (response.status === 401 || response.status === 419) {
            return `${S.sessionExpired ?? fallback} (HTTP ${response.status})`;
        }

        const errors = body?.errors;
        const detail = errors?.data?.[0] ?? errors?.json?.[0] ?? errors?.html?.[0] ?? body?.message;

        return `${detail ?? fallback} (HTTP ${response.status})`;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function toast(message, kind) {
        const existing = document.querySelector('.pb-toast');

        if (existing) {
            existing.remove();
        }

        const toast = document.createElement('div');
        toast.className = 'pb-toast pb-toast-' + (kind === 'error' ? 'error' : 'success');
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('pb-toast-show'), 20);
        setTimeout(() => {
            toast.classList.remove('pb-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }
})();