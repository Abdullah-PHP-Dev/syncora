{{-- Shared by templates/create.blade.php and templates/edit.blade.php --}}
<style>
    .socialeaz-dash .ai-banner { background: linear-gradient(135deg, rgba(124,92,255,.08), rgba(14,165,233,.06)); border: 1px solid rgba(124,92,255,.18); border-radius: .85rem; padding: 1.1rem 1.25rem; }
    .socialeaz-dash .ai-banner strong { color: var(--dash-heading); }
    .socialeaz-dash .ai-banner-icon { width: 40px; height: 40px; border-radius: .7rem; background: var(--dash-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    .socialeaz-dash .editor-toolbar { display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; border: 1px solid var(--dash-border); border-bottom: none; border-radius: .6rem .6rem 0 0; padding: .5rem; background: var(--dash-card-hover); }
    .socialeaz-dash .toolbar-btn { width: 30px; height: 30px; border-radius: .4rem; border: none; background: transparent; color: var(--dash-text); display: inline-flex; align-items: center; justify-content: center; }
    .socialeaz-dash .toolbar-btn:hover { background: var(--dash-border); color: var(--dash-primary); }
    .socialeaz-dash .toolbar-sep { width: 1px; height: 20px; background: var(--dash-border); margin: 0 .25rem; }
    .socialeaz-dash .editor-canvas {
        border: 1px solid var(--dash-border); border-radius: 0 0 .6rem .6rem; padding: 1.25rem;
        min-height: 320px; background: var(--dash-card); color: var(--dash-text); font-size: .875rem;
        overflow-y: auto;
    }
    .socialeaz-dash .editor-canvas:focus { outline: none; border-color: var(--dash-primary); }
    .socialeaz-dash .editor-canvas img { max-width: 100%; }
    .socialeaz-dash .block-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
    .socialeaz-dash .block-btn {
        display: flex; flex-direction: column; align-items: center; gap: .3rem; padding: .75rem .4rem;
        border: 1px solid var(--dash-border); border-radius: .6rem; background: var(--dash-card);
        color: var(--dash-text); font-size: .7rem; font-weight: 600;
    }
    .socialeaz-dash .block-btn i { font-size: 1.15rem; color: var(--dash-primary); }
    .socialeaz-dash .block-btn:hover { border-color: var(--dash-primary); background: var(--dash-card-hover); }
    .socialeaz-dash .ai-assist-card { background: var(--dash-card-hover); border: 1px solid var(--dash-border); border-radius: .7rem; padding: .9rem; }
    .socialeaz-dash .pro-tips-list { list-style: none; padding: 0; margin: 0; }
    .socialeaz-dash .pro-tips-list li { display: flex; align-items: flex-start; gap: .5rem; font-size: .8125rem; color: var(--dash-text); margin-bottom: .5rem; }
    .socialeaz-dash .pro-tips-list li:last-child { margin-bottom: 0; }
    .socialeaz-dash .pro-tips-list i { color: var(--dash-success); margin-top: .15rem; }

    .socialeaz-dash .social-account-chip {
        display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .7rem .3rem .3rem;
        border: 1px solid var(--dash-border); border-radius: 2rem; background: var(--dash-card);
        color: var(--dash-text); font-size: .78rem; font-weight: 600;
    }
    .socialeaz-dash .social-account-chip img { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
    .socialeaz-dash .social-account-chip-fallback { width: 24px; height: 24px; border-radius: 50%; background: var(--dash-card-hover); display: inline-flex; align-items: center; justify-content: center; color: var(--dash-muted); }
    .socialeaz-dash .social-account-chip.is-custom { padding: .3rem .7rem; }
    .socialeaz-dash .social-account-chip.is-custom i { font-size: 1rem; color: var(--dash-muted); }
    .socialeaz-dash .social-account-chip:hover { border-color: var(--dash-primary); }
    .socialeaz-dash .social-account-chip.is-selected { border-color: var(--dash-primary); background: rgba(124,92,255,.08); color: var(--dash-primary); }
</style>
