import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';

(function () {
    const cfg = window.pageBuilderConfig;

    if (!cfg) {
        return;
    }

    const S = cfg.strings;
    const B = S.blocks;
    const NATIVE_ATTRS = ['src', 'alt', 'title', 'href', 'width', 'height', 'type', 'name', 'value', 'placeholder'];

    const mediaIcon = '<svg viewBox="0 0 24 24" width="34" height="34"><path fill="currentColor" d="M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>';
    const blockIcon = '<svg viewBox="0 0 24 24" width="34" height="34"><path fill="currentColor" d="M3 5h18v14H3V5zm2 2v10h14V7H5z"/></svg>';

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

    const originalUsePicker = window.usePickerSelection;

    window.usePickerSelection = function () {
        if (typeof originalUsePicker === 'function') {
            originalUsePicker();
        }

        if (typeof window.pbApplyMediaSelection === 'function') {
            window.pbApplyMediaSelection();
        }
    };

    const statusEl = document.getElementById('pb-save-status');
    const saveNowBtn = document.getElementById('pb-save-now');
    const publishBtn = document.getElementById('pb-publish');

    let loaded = false;
    let saveTimer = null;
    let saveInFlight = false;
    let dirty = false;

    editor.on('load', () => {
        if (cfg.draft && cfg.draft.components) {
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
                toast(body?.errors?.data?.[0] ?? S.statusUnsaved, 'error');
            }
        } catch (error) {
            setStatus(S.statusUnsaved);
            toast(S.statusUnsaved, 'error');
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
                toast(body?.errors?.html?.[0] ?? body?.errors?.json?.[0] ?? S.publishError, 'error');
            }
        } catch (error) {
            setStatus(S.statusUnsaved);
            toast(S.publishError, 'error');
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

        return [
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
        ];
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