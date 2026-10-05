// Dedicated homepage entry. Links work natively without dashboard dependencies.
// Preserve back/forward navigation while scrolling to homepage sections.
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href^="#"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const target = document.getElementById(link.getAttribute('href').slice(1));
    if (!target) return;
    target.setAttribute('tabindex', '-1');
    // Native anchor navigation handles scrolling; focus makes it useful for keyboards.
    requestAnimationFrame(() => target.focus({ preventScroll: true }));
});
