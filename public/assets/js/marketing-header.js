(() => {
    const header = document.querySelector('.theme-header');
    if (!header) return;
    const dropdowns = [...header.querySelectorAll('details')];
    const close = () => dropdowns.forEach(dropdown => { dropdown.open = false; });
    const menuToggle = header.querySelector('.theme-menu-toggle');
    const closeMenu = () => {
        header.classList.remove('is-menu-open');
        menuToggle?.setAttribute('aria-expanded', 'false');
        close();
    };
    menuToggle?.addEventListener('click', () => {
        const open = header.classList.toggle('is-menu-open');
        menuToggle.setAttribute('aria-expanded', String(open));
        if (!open) close();
    });
    window.matchMedia('(max-width: 900px)').addEventListener('change', closeMenu);
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('toggle', () => {
            if (dropdown.open) dropdowns.forEach(other => { if (other !== dropdown) other.open = false; });
        });
    });
    document.addEventListener('click', event => {
        if (!header.contains(event.target)) closeMenu();
        if (event.target.closest('.theme-links a, .theme-nav-actions a')) closeMenu();
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const active = dropdowns.find(dropdown => dropdown.open);
        if (!active) {
            if (header.classList.contains('is-menu-open')) {
                closeMenu();
                menuToggle?.focus();
            }
            return;
        }
        close();
        active.querySelector('summary').focus();
    });
})();
