import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';

(function () {
    const cfg = window.pageBuilderConfig;

    if (!cfg) {
        return;
    }

    const S = cfg.strings;
    const B = S.blocks;

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
            devices: [
                { id: 'desktop', name: 'Desktop', width: '' },
                { id: 'tablet', name: 'Tablet', width: '768px', widthMedia: '992px' },
                { id: 'mobile', name: 'Mobile', width: '480px', widthMedia: '767px' },
            ],
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
            editor.setProjectData(cfg.draft);
        }

        editor.setClear();
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
                toast(body?.errors?.html?.[0] ?? S.publishError, 'error');
            }
        } catch (error) {
            setStatus(S.statusUnsaved);
            toast(S.publishError, 'error');
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
                content: '<section class="pb-section bg-light py-5"><div class="container"><div class="row"><div class="col"></div></div></div></section>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-container',
                label: B.container,
                category: categoryLayout,
                media: blockIcon,
                content: '<div class="container"></div>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-row',
                label: B.row,
                category: categoryLayout,
                media: blockIcon,
                content: '<div class="row g-3"></div>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-column',
                label: B.column,
                category: categoryLayout,
                media: blockIcon,
                content: '<div class="col"></div>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-spacer',
                label: B.spacer,
                category: categoryLayout,
                media: blockIcon,
                content: '<div class="pb-spacer" style="height: 48px;"></div>',
                select: true,
            },
            {
                id: 'pb-divider',
                label: B.divider,
                category: categoryLayout,
                media: blockIcon,
                content: '<hr class="pb-divider my-4">',
                select: true,
            },
            {
                id: 'pb-banner',
                label: B.banner,
                category: categoryLayout,
                media: blockIcon,
                content: '<section class="pb-banner text-center text-white bg-primary py-5">'
                    + '<div class="container">'
                    + '<h2 class="display-4">' + escapeHtml(B.sampleBannerTitle) + '</h2>'
                    + '<p class="lead mb-4">' + escapeHtml(B.sampleBannerLead) + '</p>'
                    + '<a class="btn btn-light btn-lg" href="#">' + escapeHtml(B.sampleBannerBtn) + '</a>'
                    + '</div></section>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-heading',
                label: B.heading,
                category: categoryContent,
                media: blockIcon,
                content: '<h2 class="display-6">' + escapeHtml(B.sampleHeading) + '</h2>',
                select: true,
            },
            {
                id: 'pb-text',
                label: B.text,
                category: categoryContent,
                media: blockIcon,
                content: '<p class="lead">' + escapeHtml(B.sampleText) + '</p>',
                select: true,
            },
            {
                id: 'pb-image',
                label: B.image,
                category: categoryContent,
                media: mediaIcon,
                content: '<img class="img-fluid pb-media-image pb-3" src="" alt="">',
                activate: true,
                select: true,
            },
            {
                id: 'pb-button',
                label: B.button,
                category: categoryContent,
                media: blockIcon,
                content: '<a class="btn btn-primary" href="#">' + escapeHtml(B.sampleButton) + '</a>',
                select: true,
            },
            {
                id: 'pb-list',
                label: B.list,
                category: categoryContent,
                media: blockIcon,
                content: '<ul class="list-group list-group-flush">'
                    + '<li class="list-group-item">' + escapeHtml(B.sampleListItem) + '</li>'
                    + '<li class="list-group-item">' + escapeHtml(B.sampleListItem) + '</li>'
                    + '</ul>',
                select: true,
            },
            {
                id: 'pb-table',
                label: B.table,
                category: categoryContent,
                media: blockIcon,
                content: '<table class="table table-striped"><thead>'
                    + '<tr><th scope="col">' + escapeHtml(B.sampleTableH1) + '</th><th scope="col">' + escapeHtml(B.sampleTableH2) + '</th></tr>'
                    + '</thead><tbody>'
                    + '<tr><td>1</td><td>2</td></tr><tr><td>3</td><td>4</td></tr>'
                    + '</tbody></table>',
                select: true,
            },
            {
                id: 'pb-card',
                label: B.card,
                category: categoryComponents,
                media: blockIcon,
                content: '<div class="card h-100"><div class="card-body">'
                    + '<h5 class="card-title">' + escapeHtml(B.sampleCardTitle) + '</h5>'
                    + '<p class="card-text">' + escapeHtml(B.sampleCardText) + '</p>'
                    + '<a class="btn btn-primary" href="#">' + escapeHtml(B.sampleCardBtn) + '</a>'
                    + '</div></div>',
                activate: true,
                select: true,
            },
            {
                id: 'pb-alert',
                label: B.alert,
                category: categoryComponents,
                media: blockIcon,
                content: '<div class="alert alert-primary" role="alert">' + escapeHtml(B.sampleAlert) + '</div>',
                select: true,
            },
            {
                id: 'pb-badge',
                label: B.badge,
                category: categoryComponents,
                media: blockIcon,
                content: '<p><span class="badge bg-primary">' + escapeHtml(B.sampleButton) + '</span></p>',
                select: true,
            },
        ];
    }

    function buildStyleManager() {
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
                },
                {
                    name: B.categoryStyleDecorations,
                    buildProps: [
                        'background-color', 'background-image', 'background-repeat',
                        'background-position', 'background-size', 'box-shadow',
                        'border-radius', 'border', 'border-color', 'border-style', 'border-width',
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
                            options: ['row', 'row-reverse', 'column', 'column-reverse'],
                        },
                        {
                            property: 'flex-wrap',
                            type: 'select',
                            defaults: 'nowrap',
                            options: ['nowrap', 'wrap', 'wrap-reverse'],
                        },
                        {
                            property: 'justify-content',
                            type: 'select',
                            options: ['flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'],
                        },
                        {
                            property: 'align-items',
                            type: 'select',
                            options: ['stretch', 'flex-start', 'center', 'flex-end', 'baseline'],
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