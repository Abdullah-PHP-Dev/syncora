(() => {
    const page = document.querySelector('[data-pricing-page]');
    if (!page) return;
    const links = page.querySelectorAll('[data-billing]');
    function applyBilling(cycle) {
        const yearly = cycle === 'yearly';
        links.forEach(link => {
            const selected = link.dataset.billing === cycle;
            link.classList.toggle('is-selected', selected);
            if (selected) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        });
        page.querySelectorAll('[data-price], [data-period], [data-billing-note]').forEach(node => {
            node.textContent = node.dataset[cycle];
        });
        page.querySelectorAll('[data-plan-cta]').forEach(link => { link.href = link.dataset[cycle]; });
        page.querySelectorAll('[data-saving]').forEach(badge => { badge.hidden = !yearly; });
    }
    links.forEach(link => link.addEventListener('click', event => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        applyBilling(link.dataset.billing);
        history.pushState(null, '', link.href);
    }));
    window.addEventListener('popstate', () => applyBilling(new URL(location.href).searchParams.get('billing') === 'yearly' ? 'yearly' : 'monthly'));
})();
