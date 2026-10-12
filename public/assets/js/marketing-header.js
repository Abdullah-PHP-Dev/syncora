(() => {
    const header = document.querySelector('.theme-header');
    if (!header) return;
    const dropdowns = [...header.querySelectorAll('details')];
    const close = () => dropdowns.forEach(dropdown => { dropdown.open = false; });
    dropdowns.forEach(dropdown => {
        dropdown.addEventListener('toggle', () => {
            if (dropdown.open) dropdowns.forEach(other => { if (other !== dropdown) other.open = false; });
        });
    });
    document.addEventListener('click', event => {
        if (!header.contains(event.target)) close();
        if (event.target.closest('.theme-product-menu-item')) close();
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const active = dropdowns.find(dropdown => dropdown.open);
        if (!active) return;
        close();
        active.querySelector('summary').focus();
    });
})();
