@extends('layouts.app')

@section('title', 'AI Copilot Settings')

@push('styles')
<style>
    .cps { --ln: #e7e9f0; --ln-soft: #f1f3f7; --ink: #161b2b; --ink2: #545d70; --muted: #8a92a3; --brand: #6d4aff; --brand-2: #8f6bff; --brand-soft: #f2eeff; --ok: #079455; --ok-soft: #ecfdf3; --warn: #b54708; --warn-soft: #fffaeb; --danger: #d92d20; color: var(--ink2); }

    /* Header */
    .cps-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
    .cps-head-title { display: flex; gap: 14px; align-items: flex-start; }
    .cps-head-icon { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; font-size: 24px; color: #fff; background: linear-gradient(135deg, var(--brand), var(--brand-2)); box-shadow: 0 8px 18px rgba(109, 74, 255, .28); flex-shrink: 0; }
    .cps-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: 2px; }
    .cps-head h4 { color: var(--ink); font-weight: 700; font-size: 1.35rem; letter-spacing: -.01em; margin: 0 0 4px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .cps-head p { margin: 0; max-width: 620px; font-size: .87rem; line-height: 1.55; }
    .cps-head p a { color: var(--brand); font-weight: 600; text-decoration: none; }
    .cps-pill { display: inline-flex; align-items: center; gap: 6px; height: 24px; padding: 0 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: 0; }
    .cps-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .cps-pill.is-off { background: var(--ln-soft); color: var(--muted); }
    .cps-pill.is-suggest { background: var(--brand-soft); color: #4f2fd6; }
    .cps-pill.is-auto { background: var(--ok-soft); color: var(--ok); }

    /* Layout */
    .cps-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start; }
    .cps-card { background: #fff; border: 1px solid var(--ln); border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); margin-bottom: 16px; }
    .cps-card-head { padding: 18px 22px 0; }
    .cps-card-head h6 { color: var(--ink); font-weight: 700; font-size: .95rem; margin: 0 0 3px; }
    .cps-card-head p { margin: 0; font-size: .82rem; color: var(--muted); }

    /* Toggle rows */
    .cps-toggle { display: flex; align-items: flex-start; gap: 16px; padding: 20px 22px; cursor: pointer; margin: 0; }
    .cps-toggle + .cps-toggle { border-top: 1px solid var(--ln-soft); }
    .cps-toggle-icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 21px; flex-shrink: 0; background: var(--ln-soft); color: var(--ink2); transition: background .2s, color .2s; }
    .cps-toggle.is-on .cps-toggle-icon { background: var(--brand-soft); color: var(--brand); }
    .cps-toggle-text { flex: 1; min-width: 0; }
    .cps-toggle-text strong { display: block; color: var(--ink); font-size: .92rem; margin-bottom: 3px; }
    .cps-toggle-text span { display: block; font-size: .82rem; line-height: 1.5; }
    .cps-toggle-text .cps-toggle-note { margin-top: 8px; display: inline-flex; align-items: center; gap: 5px; font-size: .76rem; font-weight: 600; color: var(--warn); background: var(--warn-soft); border-radius: 8px; padding: 3px 9px; }
    .cps-toggle-text .cps-toggle-note.is-hidden { display: none; }
    .cps-toggle.is-locked { cursor: not-allowed; opacity: .55; }

    .cps-switch { position: relative; width: 46px; height: 26px; flex-shrink: 0; margin-top: 4px; }
    .cps-switch input { position: absolute; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: inherit; }
    .cps-switch span { position: absolute; inset: 0; border-radius: 999px; background: #d0d5dd; transition: background .2s; pointer-events: none; }
    .cps-switch span::after { content: ''; position: absolute; top: 3px; inset-inline-start: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(16, 24, 40, .25); transition: transform .2s; }
    .cps-switch input:checked + span { background: var(--brand); }
    .cps-switch input:checked + span::after { transform: translateX(20px); }
    [dir="rtl"] .cps-switch input:checked + span::after { transform: translateX(-20px); }
    .cps-switch input:focus-visible + span { box-shadow: 0 0 0 3px rgba(109, 74, 255, .25); }

    /* Thresholds */
    .cps-thresholds { padding: 18px 22px 22px; }
    .cps-threshold { margin-bottom: 20px; }
    .cps-threshold-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 4px; }
    .cps-threshold-top label { color: var(--ink); font-weight: 700; font-size: .86rem; margin: 0; display: flex; align-items: center; gap: 8px; }
    .cps-swatch { width: 10px; height: 10px; border-radius: 3px; }
    .cps-threshold p { font-size: .78rem; color: var(--muted); margin: 0 0 10px; }
    .cps-num { display: inline-flex; align-items: center; border: 1px solid var(--ln); border-radius: 9px; overflow: hidden; }
    .cps-num input { width: 56px; height: 34px; border: none; text-align: center; font-weight: 700; color: var(--ink); font-size: .88rem; outline: none; -moz-appearance: textfield; }
    .cps-num input::-webkit-outer-spin-button, .cps-num input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .cps-num span { padding: 0 10px; height: 34px; display: grid; place-items: center; background: var(--ln-soft); color: var(--muted); font-size: .8rem; font-weight: 600; }
    .cps-num:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
    .cps-range { width: 100%; accent-color: var(--brand); cursor: pointer; }
    .cps-range.is-suggest { accent-color: #8f6bff; }

    .cps-scale { margin-top: 6px; padding: 16px; border-radius: 12px; background: #fbfbfd; border: 1px solid var(--ln-soft); }
    .cps-scale-title { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 10px; }
    .cps-bar { display: flex; height: 12px; border-radius: 999px; overflow: hidden; background: var(--ln-soft); }
    .cps-bar div { transition: width .2s; }
    .cps-bar .z-none { background: #d0d5dd; }
    .cps-bar .z-suggest { background: #b9a6ff; }
    .cps-bar .z-auto { background: linear-gradient(90deg, var(--brand), var(--brand-2)); }
    .cps-bar .z-auto.is-off { background: #b9a6ff; }
    .cps-bar-ticks { display: flex; justify-content: space-between; font-size: .68rem; color: var(--muted); margin: 4px 0 12px; font-variant-numeric: tabular-nums; }
    .cps-legend { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .cps-legend div { font-size: .76rem; line-height: 1.45; }
    .cps-legend strong { display: flex; align-items: center; gap: 6px; color: var(--ink); font-size: .78rem; margin-bottom: 2px; }
    .cps-legend em { font-style: normal; color: var(--muted); font-variant-numeric: tabular-nums; }

    .cps-error { display: none; align-items: center; gap: 6px; margin-top: 12px; font-size: .8rem; font-weight: 600; color: var(--danger); }
    .cps-error.is-visible { display: flex; }
    .cps-disabled-note { display: none; align-items: center; gap: 8px; margin: 0 22px 22px; padding: 10px 14px; border-radius: 10px; background: var(--ln-soft); font-size: .8rem; }
    .cps.is-ai-off .cps-disabled-note { display: flex; }
    .cps.is-ai-off .cps-thresholds { opacity: .55; }

    /* Save bar */
    .cps-savebar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 18px; background: #fff; border: 1px solid var(--ln); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); position: sticky; bottom: 16px; z-index: 5; transition: border-color .2s, box-shadow .2s; }
    .cps-savebar.is-dirty { border-color: #d9d0ff; box-shadow: 0 12px 32px rgba(16, 24, 40, .12); }
    .cps-savebar-status { display: flex; align-items: center; gap: 8px; font-size: .82rem; color: var(--muted); }
    .cps-savebar-status i { font-size: 1.1rem; }
    .cps-savebar.is-dirty .cps-savebar-status { color: var(--warn); font-weight: 600; }
    .cps-actions { display: flex; gap: 8px; }
    .cps-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 40px; padding: 0 18px; border-radius: 10px; font-size: .85rem; font-weight: 600; border: 1px solid transparent; cursor: pointer; text-decoration: none; transition: box-shadow .15s, transform .15s, opacity .15s, background .15s; }
    .cps-btn i { font-size: 1.05rem; }
    .cps-btn-brand { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #fff; box-shadow: 0 4px 12px rgba(109, 74, 255, .25); }
    .cps-btn-brand:hover:not(:disabled) { color: #fff; transform: translateY(-1px); }
    .cps-btn-brand:disabled { opacity: .5; box-shadow: none; cursor: not-allowed; }
    .cps-btn-ghost { background: transparent; color: var(--ink2); }
    .cps-btn-ghost:hover:not(:disabled) { background: var(--ln-soft); color: var(--ink); }
    .cps-btn-ghost:disabled { opacity: .4; }

    /* Side */
    .cps-side { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 90px; }
    .cps-side .cps-card { margin: 0; padding: 18px; }
    .cps-side h6 { color: var(--ink); font-weight: 700; font-size: .9rem; margin: 0 0 12px; }
    .cps-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
    .cps-steps li { display: flex; gap: 12px; font-size: .79rem; line-height: 1.45; color: var(--muted); }
    .cps-steps li > span { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; font-size: .72rem; font-weight: 700; color: var(--brand); background: var(--brand-soft); }
    .cps-steps strong { display: block; color: var(--ink); font-size: .82rem; }
    .cps-source { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid var(--ln); border-radius: 12px; text-decoration: none; transition: border-color .15s, background .15s; }
    .cps-source + .cps-source { margin-top: 8px; }
    .cps-source:hover { border-color: #d9d0ff; background: #fbfaff; }
    .cps-source-icon { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; background: var(--brand-soft); color: var(--brand); flex-shrink: 0; }
    .cps-source-text { flex: 1; min-width: 0; }
    .cps-source-text strong { display: block; color: var(--ink); font-size: .84rem; }
    .cps-source-text span { font-size: .75rem; color: var(--muted); }
    .cps-source-text span.is-ok { color: var(--ok); font-weight: 600; }
    .cps-source-text span.is-warn { color: var(--warn); font-weight: 600; }
    .cps-source > i { color: #c4c9d4; font-size: 1.2rem; }
    [dir="rtl"] .cps-source > i { transform: scaleX(-1); }
    .cps-guard { display: flex; gap: 10px; font-size: .78rem; line-height: 1.5; }
    .cps-guard i { color: var(--ok); font-size: 1.2rem; flex-shrink: 0; }

    @media (max-width: 991.98px) {
        .cps-grid { grid-template-columns: 1fr; }
        .cps-side { position: static; }
    }
    @media (max-width: 575.98px) {
        .cps-legend { grid-template-columns: 1fr; }
        .cps-savebar { flex-direction: column; align-items: stretch; }
        .cps-actions .cps-btn { flex: 1; }
    }
</style>
@endpush

@section('content')
@php
    $mode = !$settings->ai_enabled ? 'off' : ($settings->auto_reply_enabled ? 'auto' : 'suggest');
@endphp
<div class="cps {{ $settings->ai_enabled ? '' : 'is-ai-off' }}" id="cpsRoot">

    <div class="cps-head">
        <div class="cps-head-title">
            <div class="cps-head-icon"><i class="bx bx-bot"></i></div>
            <div>
                <div class="cps-eyebrow">AI Copilot</div>
                <h4>
                    Copilot settings
                    <span class="cps-pill is-{{ $mode }}" id="cpsModePill">{{ ['off' => 'Off', 'suggest' => 'Suggestions only', 'auto' => 'Auto-replying'][$mode] }}</span>
                </h4>
                <p>AI Copilot answers your customers from your <a href="{{ route('admin.knowledge-base.index') }}">Knowledge Base</a> and <a href="{{ route('admin.ai-copilot.business-profile.edit') }}">Business Profile</a>. It only uses your own published FAQs word for word and never invents information.</p>
            </div>
        </div>
    </div>

    <div class="cps-grid">
        <form id="ai-copilot-settings-form" novalidate>
            @csrf

            <div class="cps-card">
                <label class="cps-toggle {{ $settings->ai_enabled ? 'is-on' : '' }}" data-cps-toggle>
                    <span class="cps-toggle-icon"><i class="bx bx-bot"></i></span>
                    <span class="cps-toggle-text">
                        <strong>Enable AI Copilot</strong>
                        <span>Scores every incoming customer message against your FAQs and shows the best match in the inbox.</span>
                    </span>
                    <span class="cps-switch"><input type="checkbox" role="switch" id="ai_enabled" name="ai_enabled" {{ $settings->ai_enabled ? 'checked' : '' }}><span></span></span>
                </label>
                <label class="cps-toggle {{ $settings->auto_reply_enabled ? 'is-on' : '' }} {{ $settings->ai_enabled ? '' : 'is-locked' }}" data-cps-toggle id="cpsAutoRow">
                    <span class="cps-toggle-icon"><i class="bx bx-send"></i></span>
                    <span class="cps-toggle-text">
                        <strong>Allow automatic replies</strong>
                        <span>Sends a high-confidence answer to the customer automatically. When off, the Copilot only suggests - nothing is sent without you.</span>
                        <span class="cps-toggle-note {{ $settings->ai_enabled && $settings->auto_reply_enabled ? '' : 'is-hidden' }}" id="cpsAutoNote"><i class="bx bx-error"></i> Replies go to customers without review</span>
                    </span>
                    <span class="cps-switch"><input type="checkbox" role="switch" id="auto_reply_enabled" name="auto_reply_enabled" {{ $settings->auto_reply_enabled ? 'checked' : '' }} {{ $settings->ai_enabled ? '' : 'disabled' }}><span></span></span>
                </label>
            </div>

            <div class="cps-card">
                <div class="cps-card-head">
                    <h6>Confidence thresholds</h6>
                    <p>How sure the Copilot must be about a match before it acts.</p>
                </div>
                <div class="cps-thresholds">
                    <div class="cps-threshold">
                        <div class="cps-threshold-top">
                            <label for="confidence_threshold_auto"><span class="cps-swatch" style="background:var(--brand)"></span> Auto-reply threshold</label>
                            <div class="cps-num"><input type="number" id="confidence_threshold_auto" name="confidence_threshold_auto" min="0" max="100" value="{{ $settings->confidence_threshold_auto }}" required><span>%</span></div>
                        </div>
                        <p>A match at or above this is confident enough to send unattended.</p>
                        <input type="range" class="cps-range" min="0" max="100" step="1" value="{{ $settings->confidence_threshold_auto }}" data-cps-range="confidence_threshold_auto" aria-label="Auto-reply threshold">
                    </div>

                    <div class="cps-threshold">
                        <div class="cps-threshold-top">
                            <label for="confidence_threshold_suggested"><span class="cps-swatch" style="background:#b9a6ff"></span> Suggestion threshold</label>
                            <div class="cps-num"><input type="number" id="confidence_threshold_suggested" name="confidence_threshold_suggested" min="0" max="100" value="{{ $settings->confidence_threshold_suggested }}" required><span>%</span></div>
                        </div>
                        <p>Below this, nothing is suggested - the conversation stays in your inbox for you.</p>
                        <input type="range" class="cps-range is-suggest" min="0" max="100" step="1" value="{{ $settings->confidence_threshold_suggested }}" data-cps-range="confidence_threshold_suggested" aria-label="Suggestion threshold">
                    </div>

                    <div class="cps-scale">
                        <div class="cps-scale-title">What happens at each confidence score</div>
                        <div class="cps-bar"><div class="z-none" id="cpsZoneNone"></div><div class="z-suggest" id="cpsZoneSuggest"></div><div class="z-auto" id="cpsZoneAuto"></div></div>
                        <div class="cps-bar-ticks"><span>0%</span><span>50%</span><span>100%</span></div>
                        <div class="cps-legend">
                            <div><strong><span class="cps-swatch" style="background:#d0d5dd"></span> Left for you</strong><em id="cpsLegendNone"></em></div>
                            <div><strong><span class="cps-swatch" style="background:#b9a6ff"></span> Suggested to you</strong><em id="cpsLegendSuggest"></em></div>
                            <div><strong><span class="cps-swatch" style="background:var(--brand)"></span> <span id="cpsLegendAutoTitle">Sent automatically</span></strong><em id="cpsLegendAuto"></em></div>
                        </div>
                    </div>

                    <div class="cps-error" id="cpsThresholdError"><i class="bx bx-error-circle"></i> The auto-reply threshold can't be lower than the suggestion threshold.</div>
                </div>
                <div class="cps-disabled-note"><i class="bx bx-info-circle"></i> Thresholds apply once AI Copilot is enabled.</div>
            </div>

            <div class="cps-savebar" id="cpsSaveBar">
                <span class="cps-savebar-status" id="cpsSaveStatus"><i class="bx bx-check-circle"></i> All changes saved</span>
                <div class="cps-actions">
                    <button type="button" class="cps-btn cps-btn-ghost" id="cpsResetBtn" disabled>Discard</button>
                    <button type="submit" class="cps-btn cps-btn-brand" id="cpsSaveBtn" disabled><i class="bx bx-save"></i> Save settings</button>
                </div>
            </div>
        </form>

        <aside class="cps-side">
            <div class="cps-card">
                <h6>How it works</h6>
                <ol class="cps-steps">
                    <li><span>1</span><div><strong>A customer writes in</strong>On any connected channel.</div></li>
                    <li><span>2</span><div><strong>Copilot finds the best FAQ</strong>And scores how well it matches.</div></li>
                    <li><span>3</span><div><strong>It acts on your thresholds</strong>Sends, suggests, or leaves it for you.</div></li>
                </ol>
            </div>

            <div class="cps-card">
                <h6>Knowledge sources</h6>
                <a href="{{ route('admin.knowledge-base.index') }}" class="cps-source">
                    <span class="cps-source-icon"><i class="bx bx-book-content"></i></span>
                    <span class="cps-source-text">
                        <strong>Knowledge Base</strong>
                        <span class="{{ $publishedFaqCount ? 'is-ok' : 'is-warn' }}">{{ $publishedFaqCount ? $publishedFaqCount . ' published ' . ($publishedFaqCount === 1 ? 'FAQ' : 'FAQs') : 'No published FAQs yet' }}</span>
                    </span>
                    <i class="bx bx-chevron-right"></i>
                </a>
                <a href="{{ route('admin.ai-copilot.business-profile.edit') }}" class="cps-source">
                    <span class="cps-source-icon"><i class="bx bx-store-alt"></i></span>
                    <span class="cps-source-text">
                        <strong>Business Profile</strong>
                        <span class="{{ $hasBusinessProfile ? 'is-ok' : 'is-warn' }}">{{ $hasBusinessProfile ? 'Set up' : 'Not set up yet' }}</span>
                    </span>
                    <i class="bx bx-chevron-right"></i>
                </a>
            </div>

            <div class="cps-card">
                <div class="cps-guard"><i class="bx bx-shield-quarter"></i><span>Answers are always your own published FAQ text, sent word for word - the Copilot never writes or guesses an answer.</span></div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Delegated on document and looked up on use: this page renders inside
    // the Vue #app root, which replaces the original DOM nodes after this
    // script runs, so direct element listeners would be lost.
    (function () {
        const $ = (id) => document.getElementById(id);
        let saved = null; // last saved state, for dirty tracking / discard

        function state() {
            const form = $('ai-copilot-settings-form');
            return {
                ai_enabled: form.ai_enabled.checked,
                auto_reply_enabled: form.auto_reply_enabled.checked,
                confidence_threshold_auto: parseInt(form.confidence_threshold_auto.value, 10),
                confidence_threshold_suggested: parseInt(form.confidence_threshold_suggested.value, 10),
            };
        }

        const clamp = (v) => Math.max(0, Math.min(100, isNaN(v) ? 0 : v));

        function render() {
            const s = state();
            const auto = clamp(s.confidence_threshold_auto), suggest = clamp(s.confidence_threshold_suggested);
            const invalid = auto < suggest;

            // Mode pill + locked auto-reply row.
            const mode = !s.ai_enabled ? 'off' : (s.auto_reply_enabled ? 'auto' : 'suggest');
            const pill = $('cpsModePill');
            pill.className = 'cps-pill is-' + mode;
            pill.textContent = { off: 'Off', suggest: 'Suggestions only', auto: 'Auto-replying' }[mode];
            $('cpsRoot').classList.toggle('is-ai-off', !s.ai_enabled);
            $('ai_enabled').closest('[data-cps-toggle]').classList.toggle('is-on', s.ai_enabled);
            $('auto_reply_enabled').disabled = !s.ai_enabled;
            $('cpsAutoRow').classList.toggle('is-locked', !s.ai_enabled);
            $('cpsAutoRow').classList.toggle('is-on', s.ai_enabled && s.auto_reply_enabled);
            $('cpsAutoNote').classList.toggle('is-hidden', !(s.ai_enabled && s.auto_reply_enabled));

            // Sliders follow the number inputs.
            document.querySelectorAll('[data-cps-range]').forEach((r) => { r.value = clamp(parseInt($(r.dataset.cpsRange).value, 10)); });

            // Zone bar + legend.
            const lo = Math.min(auto, suggest), hi = Math.max(auto, suggest);
            $('cpsZoneNone').style.width = lo + '%';
            $('cpsZoneSuggest').style.width = (hi - lo) + '%';
            $('cpsZoneAuto').style.width = (100 - hi) + '%';
            $('cpsZoneAuto').classList.toggle('is-off', !s.auto_reply_enabled);
            $('cpsLegendNone').textContent = suggest > 0 ? `0–${suggest - 1}%` : '—';
            $('cpsLegendSuggest').textContent = auto > suggest ? `${suggest}–${auto - 1}%` : '—';
            $('cpsLegendAuto').textContent = `${auto}–100%`;
            $('cpsLegendAutoTitle').textContent = s.auto_reply_enabled ? 'Sent automatically' : 'Suggested (auto-reply off)';
            $('cpsThresholdError').classList.toggle('is-visible', invalid);

            // Save bar.
            const dirty = saved && JSON.stringify(s) !== JSON.stringify(saved);
            $('cpsSaveBar').classList.toggle('is-dirty', !!dirty);
            $('cpsSaveStatus').innerHTML = dirty
                ? '<i class="bx bx-edit-alt"></i> You have unsaved changes'
                : '<i class="bx bx-check-circle"></i> All changes saved';
            $('cpsSaveBtn').disabled = !dirty || invalid;
            $('cpsResetBtn').disabled = !dirty;
        }

        function apply(s) {
            const form = $('ai-copilot-settings-form');
            form.ai_enabled.checked = s.ai_enabled;
            form.auto_reply_enabled.checked = s.auto_reply_enabled;
            form.confidence_threshold_auto.value = s.confidence_threshold_auto;
            form.confidence_threshold_suggested.value = s.confidence_threshold_suggested;
            render();
        }

        function init() {
            if (!$('ai-copilot-settings-form')) return;
            saved = state();
            render();
        }

        document.addEventListener('DOMContentLoaded', init);
        window.addEventListener('load', init);

        document.addEventListener('input', function (e) {
            if (!e.target.closest('#ai-copilot-settings-form')) return;
            if (e.target.dataset.cpsRange) $(e.target.dataset.cpsRange).value = e.target.value;
            render();
        });
        document.addEventListener('change', function (e) {
            if (e.target.closest('#ai-copilot-settings-form')) render();
        });
        document.addEventListener('click', function (e) {
            if (e.target.closest('#cpsResetBtn') && saved) apply(saved);
        });

        document.addEventListener('submit', function (e) {
            const form = e.target.closest('#ai-copilot-settings-form');
            if (!form) return;
            e.preventDefault();

            const payload = state();
            if (payload.confidence_threshold_auto < payload.confidence_threshold_suggested) return render();

            const btn = $('cpsSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

            fetch('{{ route('admin.ai-copilot.settings.update') }}', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('[name=_token]').value,
                },
                body: JSON.stringify(payload),
            })
                .then(async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const first = data.errors ? Object.values(data.errors)[0][0] : null;
                        throw new Error(first || data.message || 'Could not save settings.');
                    }
                    saved = state();
                    window.Swal?.fire({ icon: 'success', title: data.message || 'Saved.', timer: 1600, showConfirmButton: false });
                })
                .catch((err) => {
                    window.Swal?.fire({ icon: 'error', title: 'Error', text: err.message });
                })
                .finally(() => {
                    btn.innerHTML = '<i class="bx bx-save"></i> Save settings';
                    render();
                });
        });
    })();
</script>
@endpush
