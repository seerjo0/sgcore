// Admin shell behaviours.

(function () {
    const aside = document.getElementById('admin-sidebar');
    const toggle = document.getElementById('admin-sidebar-toggle');
    const overlay = document.getElementById('admin-sidebar-overlay');

    if (!aside || !toggle) {
        return;
    }

    const mobile = () => window.matchMedia('(max-width: 1023.98px)').matches;

    function setOpen(open) {
        aside.setAttribute('data-open', open ? 'true' : 'false');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (overlay) {
            overlay.classList.toggle('hidden', !open);
        }

        document.body.classList.toggle('overflow-hidden', open && mobile());

        if (open) {
            const first = aside.querySelector('nav a');
            first?.focus();
        }
    }

    toggle.addEventListener('click', () => setOpen(aside.getAttribute('data-open') !== 'true'));

    overlay?.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && aside.getAttribute('data-open') === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });

    // Fecha a gaveta ao navegar (a sidebar está por cima do conteúdo no mobile).
    aside.addEventListener('click', (event) => {
        if (event.target.closest('a') && mobile()) {
            setOpen(false);
        }
    });

    // Ao voltar para o desktop, descarta o estado de gaveta.
    window.addEventListener('resize', () => {
        if (!mobile()) {
            setOpen(false);
        }
    });

    setOpen(false);
})();
