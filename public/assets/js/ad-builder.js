/*
 * Ad Builder enhancements — turns each platform's existing wizard form into the
 * Facebook-builder look WITHOUT touching its fields, cascades, or submit:
 *   Renders the objective <select> as Facebook-style clickable cards. Clicking
 *   a card sets the select's value and dispatches a native `change`, so every
 *   form's own objective->cascade logic keeps working unchanged. Step
 *   navigation and Prev/Next/Publish are left entirely to each form's wizard JS.
 *
 * Runs on `window` load, AFTER each form's own script (which also defers to
 * window load) has bound its listeners — so dispatching `change` on the objective
 * select reliably fires the cascade the form registered.
 */
(function () {
    'use strict';

    var ICON_MAP = [
        [/traffic|click|visit|website/i, 'bx-pointer'],
        [/engage|interaction/i, 'bx-heart'],
        [/lead/i, 'bx-user-plus'],
        [/sale|convert|conversion|purchase|product/i, 'bx-cart'],
        [/aware|reach|impression/i, 'bx-broadcast'],
        [/video|view/i, 'bx-video'],
        [/app|install/i, 'bx-mobile-alt'],
        [/follow/i, 'bx-user-check'],
    ];

    function iconFor(label) {
        for (var i = 0; i < ICON_MAP.length; i++) {
            if (ICON_MAP[i][0].test(label)) return ICON_MAP[i][1];
        }
        return 'bx-target-lock';
    }

    function markSelected(wrap, value) {
        var cards = wrap.querySelectorAll('.ad-objective-card');
        for (var i = 0; i < cards.length; i++) {
            cards[i].classList.toggle('selected', cards[i].getAttribute('data-value') === value);
        }
    }

    function enhanceObjective(builder) {
        var select = builder.querySelector('select[name="objective"]');
        if (!select || select.getAttribute('data-ad-cards')) return;

        var options = Array.prototype.slice.call(select.options).filter(function (o) {
            return o.value !== '';
        });
        if (!options.length) return;
        select.setAttribute('data-ad-cards', '1');

        var wrap = document.createElement('div');
        wrap.className = 'ad-objective-cards';

        options.forEach(function (opt) {
            var card = document.createElement('div');
            card.className = 'ad-objective-card';
            card.setAttribute('data-value', opt.value);
            card.innerHTML =
                '<div class="ao-icon"><i class="bx ' + iconFor(opt.textContent) + '"></i></div>' +
                '<div class="ao-label"></div>';
            card.querySelector('.ao-label').textContent = opt.textContent.trim();
            card.addEventListener('click', function () {
                select.value = opt.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                markSelected(wrap, opt.value);
            });
            wrap.appendChild(card);
        });

        select.classList.add('ad-hidden');
        select.parentNode.insertBefore(wrap, select);

        // Give the objective its own full-width row so the tiles get a clean
        // grid instead of being squeezed into a half-width Bootstrap column.
        var col = select.closest('[class*="col-"]');
        if (col) col.classList.add('ad-col-full');

        markSelected(wrap, select.value);

        // Keep cards in sync if the form's own JS changes the objective.
        select.addEventListener('change', function () { markSelected(wrap, select.value); });

        // Populate any objective-dependent fields for the initial value.
        if (select.value) {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    /* ---- Custom dropdown to replace the native <select> popup, which
       Chrome/macOS mispositions at non-100% zoom. The native <select> stays in
       the DOM as the source of truth (value + options), so the forms' cascades
       and submit are untouched; we only take over the visual dropdown. ---- */
    function closeAllSelects(except) {
        var open = document.querySelectorAll('.campaign-builder .ad-select.open');
        for (var i = 0; i < open.length; i++) { if (open[i] !== except) open[i].classList.remove('open'); }
    }

    function buildCustomSelect(sel) {
        if (sel.multiple || sel.dataset.adSelect) return;
        if (sel.classList.contains('select2-hidden-accessible')) return;
        if (sel.getAttribute('name') === 'objective') return;   // objective = cards
        if (sel.closest('.select2-container') || sel.closest('.input-group')) return;
        sel.dataset.adSelect = '1';

        var wrap = document.createElement('div');
        wrap.className = 'ad-select';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ad-select-btn';
        var lbl = document.createElement('span');
        lbl.className = 'ad-select-label';
        var caret = document.createElement('i');
        caret.className = 'bx bx-chevron-down caret';
        btn.appendChild(lbl);
        btn.appendChild(caret);
        var menu = document.createElement('div');
        menu.className = 'ad-select-menu';

        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(btn);
        wrap.appendChild(menu);
        wrap.appendChild(sel);
        sel.classList.add('ad-native-hidden');

        function currentLabel() {
            var o = sel.options[sel.selectedIndex];
            return o ? o.textContent.trim() : '';
        }
        function sync() { lbl.textContent = currentLabel(); }
        function renderMenu() {
            menu.innerHTML = '';
            Array.prototype.forEach.call(sel.options, function (o) {
                var item = document.createElement('div');
                item.className = 'ad-select-opt' + (o.selected ? ' selected' : '');
                item.textContent = o.textContent.trim();
                item.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (sel.value !== o.value) {
                        sel.value = o.value;
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    sync();
                    wrap.classList.remove('open');
                });
                menu.appendChild(item);
            });
        }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (sel.disabled) return;
            var isOpen = wrap.classList.contains('open');
            closeAllSelects();
            if (!isOpen) {
                renderMenu();
                wrap.classList.add('open');
                menu.classList.remove('drop-up');
                if (menu.getBoundingClientRect().bottom > window.innerHeight - 12) menu.classList.add('drop-up');
            }
        });

        // Stay in sync when the form's own JS changes the value or repopulates
        // the options (cascades).
        sel.addEventListener('change', sync);
        new MutationObserver(function () {
            sync();
            if (wrap.classList.contains('open')) renderMenu();
        }).observe(sel, { childList: true });

        sync();
    }

    function enhanceSelects(builder) {
        var selects = builder.querySelectorAll('select');
        for (var i = 0; i < selects.length; i++) buildCustomSelect(selects[i]);
    }

    function init() {
        // Objective -> cards, and native <select> -> custom dropdown. Step
        // navigation, Prev/Next/Publish and per-step validation are left to each
        // form's own wizard JS (the working, tested behavior).
        var builders = document.querySelectorAll('.campaign-builder');
        for (var i = 0; i < builders.length; i++) {
            enhanceObjective(builders[i]);
            enhanceSelects(builders[i]);
        }
        document.addEventListener('click', function () { closeAllSelects(); });
    }

    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }
})();
