(() => {
    const sidebar = document.querySelector('.overview-sidebar');
    if (!sidebar) return;
    const toggles = [...sidebar.querySelectorAll('.ov-sidebar-parent-toggle')];
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
