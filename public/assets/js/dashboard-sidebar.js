(() => {
    const root = document.documentElement;
    const mobile = window.matchMedia('(max-width: 1199.98px)');
    const sync = () => {
        const expanded = mobile.matches ? root.classList.contains('layout-menu-expanded') : !root.classList.contains('layout-menu-collapsed');
        document.querySelectorAll('.admin-menu-toggle, .admin-sidebar-collapse').forEach(button => {
            button.setAttribute('aria-expanded', String(expanded));
            button.setAttribute('aria-controls', 'layout-menu');
        });
    };
    // Capture before the vendor handler, which depends on menu initialization.
    document.addEventListener('click', event => {
        const toggle = event.target.closest('.admin-menu-toggle, .admin-sidebar-collapse, .layout-overlay');
        if (!toggle) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        root.classList.remove('layout-menu-hover', 'layout-transitioning');
        if (mobile.matches) {
            root.classList.remove('layout-menu-collapsed');
            root.classList.toggle('layout-menu-expanded');
        } else {
            root.classList.toggle('layout-menu-collapsed');
        }
        sync();
    }, true);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && mobile.matches) {
            root.classList.remove('layout-menu-expanded');
            sync();
        }
    });
    mobile.addEventListener('change', () => {
        root.classList.remove('layout-menu-expanded');
        if (mobile.matches) root.classList.remove('layout-menu-collapsed');
        sync();
    });
    document.addEventListener('DOMContentLoaded', sync);
})();
