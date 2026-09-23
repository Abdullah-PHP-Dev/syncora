{{--
    Shared by templates/create.blade.php and templates/edit.blade.php.

    Only the AI banner above the block editor lives in plain Blade/CSS -
    the editor itself (blocks panel, canvas, property inspector, preview)
    is the <email-template-designer> Vue component
    (resources/js/components/email/EmailTemplateDesigner.vue), which
    carries its own scoped styles and reuses .device-toggle/.browser-chrome/
    .template-preview-frame from layouts/partials/dash-styles.blade.php
    rather than redeclaring them here.
--}}
<style>
    .socialeaz-dash .ai-banner { background: linear-gradient(135deg, rgba(124,92,255,.08), rgba(14,165,233,.06)); border: 1px solid rgba(124,92,255,.18); border-radius: .85rem; padding: 1.1rem 1.25rem; }
    .socialeaz-dash .ai-banner strong { color: var(--dash-heading); }
    .socialeaz-dash .ai-banner-icon { width: 40px; height: 40px; border-radius: .7rem; background: var(--dash-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
</style>
