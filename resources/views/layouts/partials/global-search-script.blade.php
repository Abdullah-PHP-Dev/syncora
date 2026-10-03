{{-- Behaviour for the navbar ⌘K search (see navbar.blade.php); styles live in
     socialeaz-admin.css - the navbar renders after the head's styles stack. --}}
@once
@push('scripts')
<script>
    (function () {
        // Delegated + looked up on use: the navbar sits inside the Vue
        // #app root, which re-mounts after load.
        const $ = (id) => document.getElementById(id);
        let timer = null, controller = null, items = [], active = -1;

        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const panel = () => $('seSearchResults');

        function close() {
            const p = panel();
            if (p) p.hidden = true;
            $('seGlobalSearch')?.setAttribute('aria-expanded', 'false');
            active = -1;
        }

        function highlight(i) {
            items = Array.from(panel().querySelectorAll('.se-search-item'));
            items.forEach((el, n) => el.classList.toggle('is-active', n === i));
            active = i;
            items[i]?.scrollIntoView({ block: 'nearest' });
        }

        function render(groups, term) {
            const p = panel();
            if (!groups.length) {
                p.innerHTML = `<div class="se-search-empty">{{ __('No results for') }} “${esc(term)}”</div>`;
            } else {
                p.innerHTML = groups.map((g) => `<div class="se-search-group">${esc(g.label)}</div>` + g.items.map((it) => `
                    <a class="se-search-item" role="option" href="${esc(it.url)}">
                        <span class="se-search-item-icon"><i class="bx ${esc(it.icon)}"></i></span>
                        <span class="se-search-item-text"><strong>${esc(it.title)}</strong><small>${esc(it.subtitle)}</small></span>
                    </a>`).join('')).join('') +
                    `<div class="se-search-hint"><span><kbd>↑</kbd> <kbd>↓</kbd> {{ __('navigate') }}</span><span><kbd>↵</kbd> {{ __('open') }}</span><span><kbd>esc</kbd> {{ __('close') }}</span></div>`;
            }
            p.hidden = false;
            $('seGlobalSearch').setAttribute('aria-expanded', 'true');
            highlight(groups.length ? 0 : -1);
        }

        function search(term) {
            const box = document.querySelector('.se-search');
            if (term.length < 2) return close();
            controller?.abort();
            controller = new AbortController();
            fetch(box.dataset.searchUrl + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((r) => r.json())
                .then((d) => render(d.groups || [], term))
                .catch(() => {});
        }

        document.addEventListener('input', (e) => {
            if (e.target.id !== 'seGlobalSearch') return;
            clearTimeout(timer);
            timer = setTimeout(() => search(e.target.value.trim()), 200);
        });

        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                const input = $('seGlobalSearch');
                if (input) { e.preventDefault(); input.focus(); input.select(); }
                return;
            }
            if (e.target.id !== 'seGlobalSearch' || panel()?.hidden) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, items.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
            else if (e.key === 'Enter' && items[active]) { e.preventDefault(); window.location.href = items[active].href; }
            else if (e.key === 'Escape') { close(); }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.se-search')) close();
        });
        document.addEventListener('focusin', (e) => {
            if (e.target.id === 'seGlobalSearch' && e.target.value.trim().length >= 2) search(e.target.value.trim());
        });
    })();
</script>
@endpush
@endonce
