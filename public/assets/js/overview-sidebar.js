(() => {
    const sidebar = document.querySelector('.overview-sidebar');
    if (!sidebar) return;
    const toggles = [...sidebar.querySelectorAll('.ov-sidebar-dropdown-toggle')];
    const setOpen = (toggle, open) => {
        toggle.setAttribute('aria-expanded', String(open));
        const submenu = document.getElementById(toggle.getAttribute('aria-controls'));
        if (submenu) submenu.hidden = !open;
    };
    toggles.forEach(toggle => toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        if (open && document.documentElement.classList.contains('layout-menu-collapsed')) {
            sidebar.querySelector('.admin-sidebar-collapse')?.click();
        }
        toggles.forEach(other => setOpen(other, other === toggle && open));
    }));
    // Icons in the collapsed rail open the module's submenu first.
    sidebar.querySelectorAll('.ov-sidebar-dropdown > .ov-sidebar-link').forEach(link => {
        link.addEventListener('click', event => {
            if (!document.documentElement.classList.contains('layout-menu-collapsed')) return;
            event.preventDefault();
            link.parentElement.querySelector('.ov-sidebar-dropdown-toggle').click();
        });
    });
    sidebar.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const open = toggles.find(toggle => toggle.getAttribute('aria-expanded') === 'true');
        if (open) {
            event.stopPropagation();
            setOpen(open, false);
            open.focus();
        }
    });
})();
