@extends('layouts.app')

@section('title', 'Create ChatGPT Ads Campaign')

{{--
    ChatGPT Ads campaign builder (App\Services\AdServices\OpenAiAdService::store).
    One submit creates the campaign, its ad group and one chat-card ad; OpenAI
    reviews every ad before it serves. Posted with fetch so validation errors
    show inline without losing the picked image.
--}}

@push('styles')
<style>
    .oa { --ink: #09072d; --ink2: #374151; --muted: #6b7280; --soft: #9ca3af; --ln: #e8e8ee; --bg: #f7f7f8; --brand: #004cff; --brand2: #710bff; --brand-soft: #eef3ff; --grad: linear-gradient(110deg, #004cff, #710bff); --ok: #12b76a; --ok-soft: #e8f8ef; --danger: #dc2626; max-width: 1220px; margin: 0 auto; padding-bottom: 96px; }

    /* hero */
    .oa-hero { position: relative; overflow: hidden; border-radius: 22px; padding: 26px 28px; margin-bottom: 18px; color: #fff;
        background: radial-gradient(520px 240px at 95% -20%, rgba(255,255,255,.28), transparent 70%), radial-gradient(420px 220px at 0% 130%, rgba(167,139,250,.45), transparent 70%), linear-gradient(110deg, #004cff 0%, #3b2bff 55%, #710bff 100%); box-shadow: 0 18px 40px rgba(0,76,255,.22); }
    .oa-hero::after { content: ''; position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.1) 1px, transparent 1px); background-size: 18px 18px; pointer-events: none; }
    .oa-hero-row { position: relative; z-index: 1; display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
    .oa-logo { width: 56px; height: 56px; border-radius: 16px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); display: grid; place-items: center; font-size: 28px; backdrop-filter: blur(6px); }
    .oa-hero h3 { margin: 0; font-weight: 700; color: #fff; font-size: 1.45rem; letter-spacing: -.01em; }
    .oa-hero p { margin: 4px 0 0; color: rgba(255,255,255,.85); font-size: .9rem; }
    .oa-hero-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
    .oa-hero-tags span { font-size: .74rem; font-weight: 600; padding: 5px 10px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); color: rgba(255,255,255,.88); display: inline-flex; align-items: center; gap: 5px; }
    .oa-hero-tags span i { font-size: 14px; color: #fff; }
    .oa-hero .oa-back { margin-left: auto; color: #fff; border: 1px solid rgba(255,255,255,.25); border-radius: 12px; padding: 8px 14px; font-size: .84rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: .2s; }
    .oa-hero .oa-back:hover { background: #fff; color: var(--brand); }

    /* step nav */
    .oa-steps { position: sticky; top: 76px; z-index: 20; display: flex; gap: 6px; padding: 6px; margin-bottom: 18px; background: rgba(255,255,255,.88); backdrop-filter: blur(10px); border: 1px solid var(--ln); border-radius: 16px; box-shadow: 0 6px 20px rgba(16,24,40,.05); overflow-x: auto; }
    .oa-steps a { flex: 1; min-width: 140px; display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 11px; color: var(--muted); text-decoration: none; font-size: .84rem; font-weight: 600; transition: .2s; white-space: nowrap; }
    .oa-steps a:hover { background: var(--bg); color: var(--ink); }
    .oa-steps a.current { background: var(--grad); color: #fff; box-shadow: 0 6px 16px rgba(0,76,255,.25); }
    .oa-steps .dot { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: .74rem; border: 1.5px solid currentColor; flex: none; }
    .oa-steps a.done .dot { background: var(--ok); border-color: var(--ok); color: #fff; }
    .oa-steps a.current.done .dot { background: #fff; border-color: #fff; color: var(--brand); }
    .oa-steps a.done .dot b { display: none; }
    .oa-steps a:not(.done) .dot i { display: none; }

    .oa-grid { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 22px; align-items: start; }

    /* section cards */
    .oa-sec { background: #fff; border: 1px solid var(--ln); border-radius: 18px; box-shadow: 0 1px 2px rgba(16,24,40,.04); margin-bottom: 18px; scroll-margin-top: 150px; transition: box-shadow .2s, border-color .2s; }
    .oa-sec:focus-within { border-color: #d4d4dc; box-shadow: 0 10px 30px rgba(16,24,40,.07); }
    .oa-sec-h { display: flex; align-items: center; gap: 14px; padding: 18px 22px 0; }
    .oa-ico { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 21px; flex: none; }
    .oa-ico.c1 { background: #eef2ff; color: #4f46e5; } .oa-ico.c2 { background: #fff7ed; color: #ea580c; } .oa-ico.c3 { background: var(--brand-soft); color: var(--brand); } .oa-ico.c4 { background: #fdf2f8; color: #db2777; }
    .oa-sec-h h5 { margin: 0; font-size: 1.02rem; font-weight: 700; color: var(--ink); }
    .oa-sec-h p { margin: 2px 0 0; font-size: .8rem; color: var(--muted); }
    .oa-check { margin-left: auto; font-size: .74rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: var(--bg); color: var(--soft); display: inline-flex; align-items: center; gap: 4px; transition: .2s; }
    .oa-check.ok { background: var(--ok-soft); color: var(--ok); }
    .oa-sec-b { padding: 18px 22px 22px; }

    /* fields */
    .oa .form-label { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; font-weight: 600; font-size: .83rem; color: var(--ink2); margin-bottom: 6px; }
    .oa .form-label .opt { font-weight: 500; color: var(--soft); font-size: .74rem; }
    .oa .form-control { border-radius: 11px; border-color: #e2e2e8; padding: .62rem .85rem; font-size: .9rem; transition: border-color .15s, box-shadow .15s; }
    .oa .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(0,76,255,.1); }
    .oa .form-control.is-invalid { border-color: var(--danger); box-shadow: 0 0 0 4px rgba(220,38,38,.08); }
    .oa select.form-control { appearance: none; background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E") no-repeat right .8rem center; padding-right: 2.2rem; }
    .oa-money { position: relative; }
    .oa-money > span { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-weight: 600; font-size: .9rem; pointer-events: none; }
    .oa-money .form-control { padding-left: 1.75rem; }
    .oa-money > small { position: absolute; right: .85rem; top: 50%; transform: translateY(-50%); color: var(--soft); font-size: .74rem; pointer-events: none; }
    .oa-hint { font-size: .75rem; color: var(--muted); margin-top: 6px; }
    .oa-err { color: var(--danger); font-size: .76rem; margin-top: 5px; font-weight: 500; }
    .oa-err:empty { display: none; }
    .oa-count { font-weight: 600; font-size: .72rem; color: var(--soft); font-variant-numeric: tabular-nums; }
    .oa-count.near { color: #d97706; } .oa-count.over { color: var(--danger); }

    .oa-choice { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .oa-choice label { position: relative; border: 1.5px solid var(--ln); border-radius: 14px; padding: 14px; cursor: pointer; display: flex; gap: 12px; align-items: flex-start; transition: .15s; margin: 0; }
    .oa-choice label:hover { border-color: #cfcfd8; }
    .oa-choice input { position: absolute; opacity: 0; pointer-events: none; }
    .oa-choice .ci { width: 36px; height: 36px; border-radius: 10px; background: var(--bg); display: grid; place-items: center; font-size: 19px; color: var(--ink2); flex: none; transition: .15s; }
    .oa-choice strong { display: block; font-size: .88rem; color: var(--ink); }
    .oa-choice small { color: var(--muted); font-size: .76rem; line-height: 1.35; }
    .oa-choice .tick { position: absolute; top: 10px; right: 10px; width: 18px; height: 18px; border-radius: 50%; border: 1.5px solid #d4d4dc; display: grid; place-items: center; font-size: 12px; color: transparent; transition: .15s; }
    .oa-choice label:has(input:checked) { border-color: var(--brand); background: #f6f8ff; box-shadow: 0 0 0 3px rgba(0,76,255,.08); }
    .oa-choice label:has(input:checked) .ci { background: var(--grad); color: #fff; }
    .oa-choice label:has(input:checked) .tick { background: var(--brand); border-color: var(--brand); color: #fff; }
    .oa-choice label:has(input:focus-visible) { outline: 2px solid var(--brand); outline-offset: 2px; }

    /* upload */
    .oa-drop { border: 1.5px dashed #d4d4dc; border-radius: 16px; padding: 26px 18px; text-align: center; cursor: pointer; background: var(--bg); transition: .2s; display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .oa-drop:hover, .oa-drop.drag { border-color: var(--brand); background: var(--brand-soft); }
    .oa-drop .up { width: 48px; height: 48px; border-radius: 14px; background: #fff; box-shadow: 0 2px 8px rgba(16,24,40,.08); display: grid; place-items: center; font-size: 24px; color: var(--brand); }
    .oa-drop strong { font-size: .9rem; color: var(--ink); }
    .oa-drop strong u { text-decoration: none; color: var(--brand); }
    .oa-drop small { color: var(--muted); font-size: .76rem; }
    .oa-file { display: none; align-items: center; gap: 12px; border: 1px solid var(--ln); border-radius: 14px; padding: 10px; }
    .oa-file.show { display: flex; }
    .oa-file img { width: 64px; height: 64px; border-radius: 10px; object-fit: cover; flex: none; }
    .oa-file div { min-width: 0; flex: 1; }
    .oa-file strong { display: block; font-size: .86rem; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oa-file small { color: var(--muted); font-size: .76rem; }
    .oa-file button { border: 0; background: var(--bg); width: 34px; height: 34px; border-radius: 10px; color: var(--muted); font-size: 18px; display: grid; place-items: center; }
    .oa-file button:hover { background: #fee2e2; color: var(--danger); }

    /* media gallery (MediaInputAttach) restyled to this page; its own
       strip is hidden because the file card above already shows the pick */
    .oa-or { display: flex; align-items: center; gap: 10px; margin: 12px 0 0; color: var(--soft); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .oa-or::before, .oa-or::after { content: ''; flex: 1; height: 1px; background: var(--ln); }
    .oa-media .mia { margin-top: 10px; }
    .oa-media .mia .mia-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .oa-media .mia .mia-btn { height: 46px; justify-content: center; border: 1.5px solid var(--ln); border-radius: 12px; color: var(--ink); background: #fff; font-size: .85rem; transition: .15s; }
    .oa-media .mia .mia-btn i { font-size: 18px; color: var(--brand); }
    .oa-media .mia .mia-btn:hover:not(:disabled) { border-color: var(--brand); background: var(--brand-soft); }
    .oa-media .mia .mia-strip { display: none; }
    .oa-file small b { color: var(--brand); font-weight: 600; }

    /* side */
    .oa-side { position: sticky; top: 148px; }
    .oa-panel { background: #fff; border: 1px solid var(--ln); border-radius: 18px; box-shadow: 0 1px 2px rgba(16,24,40,.04); margin-bottom: 16px; overflow: hidden; }
    .oa-panel-h { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #f0f0f4; }
    .oa-panel-h strong { font-size: .9rem; color: var(--ink); display: flex; align-items: center; gap: 6px; }
    .oa-panel-h small { font-size: .72rem; color: var(--soft); }

    .oa-gpt { background: #fff; padding: 16px 16px 12px; }
    .oa-gpt-top { display: flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 600; color: var(--ink2); margin-bottom: 14px; }
    .oa-gpt-top i { font-size: 16px; }
    .oa-msg-me { margin-left: auto; max-width: 80%; width: fit-content; background: #f1f1f3; border-radius: 18px; padding: 9px 14px; font-size: .82rem; color: var(--ink); margin-bottom: 12px; }
    .oa-msg-ai { display: flex; gap: 8px; font-size: .82rem; color: var(--ink2); line-height: 1.5; margin-bottom: 10px; }
    .oa-msg-ai .av { width: 24px; height: 24px; border-radius: 50%; border: 1px solid var(--ln); display: grid; place-items: center; font-size: 14px; flex: none; }
    .oa-msg-ai .ln { height: 8px; border-radius: 4px; background: #efeff2; margin: 6px 0; }
    .oa-sponsor { font-size: .68rem; color: var(--soft); margin: 2px 0 6px 32px; font-weight: 600; letter-spacing: .02em; }
    .oa-adcard { margin-left: 32px; border: 1px solid var(--ln); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 14px rgba(16,24,40,.06); transition: .2s; }
    .oa-adcard .img { aspect-ratio: 1.91 / 1; background: linear-gradient(135deg, #f3f4f6, #e5e7eb); display: grid; place-items: center; color: var(--soft); font-size: 30px; }
    .oa-adcard .img img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .oa-adcard .txt { padding: 10px 12px 12px; }
    .oa-adcard .host { display: flex; align-items: center; gap: 5px; color: var(--muted); font-size: .7rem; margin-bottom: 4px; }
    .oa-adcard .host i { font-size: 12px; }
    .oa-adcard strong { display: block; font-size: .88rem; color: var(--ink); word-break: break-word; line-height: 1.3; }
    .oa-adcard p { margin: 3px 0 0; font-size: .78rem; color: var(--muted); word-break: break-word; line-height: 1.4; }
    .oa-adcard .ph { color: var(--soft); }
    .oa-gpt-input { margin-top: 14px; border: 1px solid var(--ln); border-radius: 999px; padding: 8px 8px 8px 14px; display: flex; align-items: center; font-size: .78rem; color: var(--soft); }
    .oa-gpt-input span { margin-left: auto; width: 26px; height: 26px; border-radius: 50%; background: var(--ink); color: #fff; display: grid; place-items: center; font-size: 15px; }

    .oa-sum { padding: 6px 18px 4px; }
    .oa-sum-row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px dashed #ececf1; font-size: .8rem; }
    .oa-sum-row:last-child { border-bottom: 0; }
    .oa-sum-row span { color: var(--muted); }
    .oa-sum-row strong { color: var(--ink); text-align: right; font-weight: 600; }
    .oa-ready { padding: 14px 18px 16px; border-top: 1px solid #f0f0f4; }
    .oa-ready-bar { height: 6px; border-radius: 999px; background: #f0f0f4; overflow: hidden; margin: 8px 0 12px; }
    .oa-ready-bar i { display: block; height: 100%; width: 0; background: var(--grad); border-radius: inherit; transition: width .3s; }
    .oa-ready-top { display: flex; justify-content: space-between; font-size: .8rem; font-weight: 700; color: var(--ink); }
    .oa-ready-top span { color: var(--muted); font-weight: 600; }
    .oa-ready ul { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px; }
    .oa-ready li { font-size: .76rem; color: var(--muted); display: flex; align-items: center; gap: 5px; }
    .oa-ready li i { font-size: 15px; color: #d4d4dc; }
    .oa-ready li.ok { color: var(--ink2); }
    .oa-ready li.ok i { color: var(--ok); }
    .oa-note { display: flex; gap: 10px; padding: 12px 14px; border-radius: 14px; background: #fffbeb; border: 1px solid #fde68a; font-size: .76rem; color: #92400e; line-height: 1.45; }
    .oa-note i { font-size: 17px; flex: none; }

    /* alert + launch bar */
    .oa-alert { border-radius: 14px; padding: 12px 16px; font-size: .86rem; margin-bottom: 16px; display: none; align-items: center; gap: 10px; font-weight: 500; }
    .oa-alert.show { display: flex; }
    .oa-alert.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .oa-alert.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .oa-bar { position: sticky; bottom: 16px; z-index: 30; margin-top: 4px; display: flex; align-items: center; gap: 14px; padding: 12px 14px 12px 20px; border-radius: 18px; background: var(--grad); color: #fff; box-shadow: 0 18px 40px rgba(0,76,255,.3); }
    .oa-bar-txt { min-width: 0; }
    .oa-bar-txt strong { display: block; font-size: .9rem; }
    .oa-bar-txt small { color: rgba(255,255,255,.8); font-size: .76rem; }
    .oa-bar .cancel { margin-left: auto; color: rgba(255,255,255,.9); font-size: .84rem; font-weight: 600; text-decoration: none; padding: 10px 12px; border-radius: 12px; }
    .oa-bar .cancel:hover { color: #fff; background: rgba(255,255,255,.14); }
    .oa-bar .go { border: 0; border-radius: 12px; padding: 11px 20px; font-weight: 700; font-size: .88rem; color: var(--brand); background: #fff; box-shadow: 0 6px 16px rgba(9,7,45,.18); display: inline-flex; align-items: center; gap: 7px; transition: .2s; white-space: nowrap; }
    .oa-bar .go:hover { filter: brightness(1.06); transform: translateY(-1px); }
    .oa-bar .go:disabled { opacity: .7; transform: none; }

    /* empty state */
    .oa-empty { background: #fff; border: 1px solid var(--ln); border-radius: 22px; text-align: center; padding: 56px 24px; }
    .oa-empty .ic { width: 64px; height: 64px; margin: 0 auto 14px; border-radius: 18px; background: var(--grad); color: #fff; display: grid; place-items: center; font-size: 30px; }
    .oa-empty h5 { font-weight: 700; color: var(--ink); }
    .oa-empty p { color: var(--muted); max-width: 420px; margin: 6px auto 18px; font-size: .9rem; }
    .oa-empty a { background: var(--grad); color: #fff; border-radius: 12px; padding: 10px 18px; font-weight: 600; text-decoration: none; display: inline-flex; gap: 6px; align-items: center; }

    @media (max-width: 1199.98px) { .oa-grid { grid-template-columns: minmax(0, 1fr) 330px; } }
    @media (max-width: 991.98px) { .oa-grid { grid-template-columns: 1fr; } .oa-side { position: static; } .oa-steps { top: 64px; } }
    @media (max-width: 575.98px) { .oa-hero { padding: 20px; } .oa-choice { grid-template-columns: 1fr; } .oa-sec-h, .oa-sec-b { padding-left: 16px; padding-right: 16px; } .oa-bar-txt small { display: none; } .oa-bar .cancel { display: none; } .oa-bar .go { margin-left: auto; } .oa-steps a { min-width: auto; justify-content: center; } .oa-steps a > span:not(.dot) { display: none; } }
</style>
@endpush

@section('content')
@php $indexUrl = route('admin.ads.campaigns.index', ['platform' => 'openai']); @endphp
<div class="oa">
    <div class="oa-hero">
        <div class="oa-hero-row">
            <span class="oa-logo"><i class="bx bx-bot"></i></span>
            <div>
                <h3>Create a ChatGPT Ads campaign</h3>
                <p>Reach people while they ask ChatGPT for recommendations. Set it up once and we build the campaign, ad group and ad for you.</p>
                <div class="oa-hero-tags">
                    <span><i class="bx bx-message-square-detail"></i> Chat-card ad</span>
                    <span><i class="bx bx-shield-quarter"></i> Reviewed by OpenAI</span>
                    <span><i class="bx bx-time-five"></i> About 3 minutes</span>
                </div>
            </div>
            <a href="{{ $indexUrl }}" class="oa-back"><i class="bx bx-arrow-back"></i> Campaigns</a>
        </div>
    </div>

    @include('admin.ads.partials.account-switcher')

    @if (! $account)
        <div class="oa-empty">
            <div class="ic"><i class="bx bx-key"></i></div>
            <h5>Connect ChatGPT Ads first</h5>
            <p>Add your OpenAI Ads API key in Connections. It takes a minute, then you'll come straight back here.</p>
            <a href="{{ \App\Support\Connections\HubLink::for('openai') ?? route('admin.connections.index') }}"><i class="bx bx-link"></i> Connect ChatGPT Ads</a>
        </div>
    @else
    <form id="oaForm" action="{{ route('admin.ads.campaigns.store', ['platform' => 'openai']) }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf

        <nav class="oa-steps" id="oaSteps">
            <a href="#sec-campaign" data-step="campaign" class="current"><span class="dot"><b>1</b><i class="bx bx-check"></i></span><span>Campaign</span></a>
            <a href="#sec-bidding" data-step="bidding"><span class="dot"><b>2</b><i class="bx bx-check"></i></span><span>Bidding</span></a>
            <a href="#sec-ad" data-step="ad"><span class="dot"><b>3</b><i class="bx bx-check"></i></span><span>Chat-card ad</span></a>
            <a href="#sec-launch" data-step="launch"><span class="dot"><b>4</b><i class="bx bx-check"></i></span><span>Launch</span></a>
        </nav>

        <div id="oaAlert" class="oa-alert" role="alert"></div>

        <div class="oa-grid">
            <div>
                <section class="oa-sec" id="sec-campaign">
                    <div class="oa-sec-h">
                        <span class="oa-ico c1"><i class="bx bx-target-lock"></i></span>
                        <div><h5>Campaign</h5><p>Name, total budget and when it runs</p></div>
                        <span class="oa-check" data-check="campaign"><i class="bx bx-check-circle"></i> <span>Required</span></span>
                    </div>
                    <div class="oa-sec-b row g-3">
                        <div class="col-12">
                            <label class="form-label" for="name">Campaign name</label>
                            <input class="form-control" id="name" name="name" maxlength="1000" placeholder="e.g. Spring sale – ChatGPT" required>
                            <div class="oa-err" data-err="name"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="budget">Lifetime budget</label>
                            <div class="oa-money"><span>$</span><input class="form-control" type="number" id="budget" name="budget" min="1" step="0.01" placeholder="500" required><small>USD</small></div>
                            <div class="oa-hint">The most this campaign can spend in total.</div>
                            <div class="oa-err" data-err="budget"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="start_time">Start date <span class="opt">Optional</span></label>
                            <input class="form-control" type="date" id="start_time" name="start_time" min="{{ now()->toDateString() }}">
                            <div class="oa-hint">Leave empty to start once approved.</div>
                            <div class="oa-err" data-err="start_time"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="end_time">End date <span class="opt">Optional</span></label>
                            <input class="form-control" type="date" id="end_time" name="end_time" min="{{ now()->toDateString() }}">
                            <div class="oa-hint">Leave empty to run until the budget is spent.</div>
                            <div class="oa-err" data-err="end_time"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Goal</label>
                            <div class="oa-choice">
                                <label><input type="radio" name="bidding_type" value="clicks" checked><span class="ci"><i class="bx bx-pointer"></i></span><span><strong>Website visits</strong><small>Optimise for clicks to your landing page</small></span><span class="tick"><i class="bx bx-check"></i></span></label>
                                <label><input type="radio" name="bidding_type" value="impressions"><span class="ci"><i class="bx bx-show"></i></span><span><strong>Awareness</strong><small>Optimise to be seen by as many people as possible</small></span><span class="tick"><i class="bx bx-check"></i></span></label>
                            </div>
                            <div class="oa-err" data-err="bidding_type"></div>
                        </div>
                    </div>
                </section>

                <section class="oa-sec" id="sec-bidding">
                    <div class="oa-sec-h">
                        <span class="oa-ico c2"><i class="bx bx-coin-stack"></i></span>
                        <div><h5>Bidding</h5><p>How you pay and the most you'll bid</p></div>
                        <span class="oa-check" data-check="bidding"><i class="bx bx-check-circle"></i> <span>Required</span></span>
                    </div>
                    <div class="oa-sec-b row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="billing_event">Pay per</label>
                            <select class="form-control" id="billing_event" name="billing_event">
                                <option value="click">Click</option>
                                <option value="impression">1,000 impressions</option>
                            </select>
                            <div class="oa-err" data-err="billing_event"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="max_bid">Max bid</label>
                            <div class="oa-money"><span>$</span><input class="form-control" type="number" id="max_bid" name="max_bid" min="0.01" step="0.01" placeholder="1.50"><small id="bidUnit">per click</small></div>
                            <div class="oa-err" data-err="max_bid"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ad_group_name">Ad group name <span class="opt">Optional</span></label>
                            <input class="form-control" id="ad_group_name" name="ad_group_name" placeholder="Uses the campaign name if left empty">
                            <div class="oa-err" data-err="ad_group_name"></div>
                        </div>
                    </div>
                </section>

                <section class="oa-sec" id="sec-ad">
                    <div class="oa-sec-h">
                        <span class="oa-ico c3"><i class="bx bx-message-square-detail"></i></span>
                        <div><h5>Chat-card ad</h5><p>Shown below relevant ChatGPT answers</p></div>
                        <span class="oa-check" data-check="ad"><i class="bx bx-check-circle"></i> <span>Required</span></span>
                    </div>
                    <div class="oa-sec-b row g-3">
                        <div class="col-12">
                            <label class="form-label" for="title">Headline <span class="oa-count" data-count="title">0/50</span></label>
                            <input class="form-control" id="title" name="title" maxlength="50" placeholder="Fresh bread, delivered by 8am" required>
                            <div class="oa-err" data-err="title"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="body">Description <span class="oa-count" data-count="body">0/100</span></label>
                            <textarea class="form-control" id="body" name="body" rows="2" maxlength="100" placeholder="Order tonight, wake up to warm sourdough at your door."></textarea>
                            <div class="oa-err" data-err="body"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="target_link">Landing page</label>
                            <input class="form-control" type="url" id="target_link" name="target_link" placeholder="https://example.com/offer" required>
                            <div class="oa-err" data-err="target_link"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Image</label>
                            <div class="oa-media">
                            <div class="oa-drop" id="oaDrop" role="button" tabindex="0">
                                <span class="up"><i class="bx bx-cloud-upload"></i></span>
                                <strong>Drop an image here or <u>browse</u></strong>
                                <small>JPG, PNG or WebP · up to 5 MB · a wide image (1.91:1) looks best</small>
                            </div>
                            <div class="oa-file" id="oaFile">
                                <img id="oaFileImg" alt="">
                                <div><strong id="oaFileName"></strong><small id="oaFileSize"></small></div>
                                <button type="button" id="oaFileRemove" title="Remove image"><i class="bx bx-trash"></i></button>
                            </div>
                            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" hidden>
                            <div class="oa-or"><span>or</span></div>
                            @include('admin.media-gallery.partials.attach', ['platform' => 'openai', 'target' => '#image'])
                            </div>
                            <div class="oa-err" data-err="image"></div>
                        </div>
                    </div>
                </section>

                <section class="oa-sec" id="sec-launch">
                    <div class="oa-sec-h">
                        <span class="oa-ico c4"><i class="bx bx-rocket"></i></span>
                        <div><h5>Launch</h5><p>What happens once OpenAI approves the ad</p></div>
                        <span class="oa-check" data-check="launch"><i class="bx bx-check-circle"></i> <span>Ready</span></span>
                    </div>
                    <div class="oa-sec-b">
                        <div class="oa-choice">
                            <label><input type="radio" name="launch" value="active" checked><span class="ci"><i class="bx bx-play"></i></span><span><strong>Go live after approval</strong><small>Starts serving as soon as the ad is approved</small></span><span class="tick"><i class="bx bx-check"></i></span></label>
                            <label><input type="radio" name="launch" value="paused"><span class="ci"><i class="bx bx-pause"></i></span><span><strong>Save as paused</strong><small>Activate it yourself later from Campaigns</small></span><span class="tick"><i class="bx bx-check"></i></span></label>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="oa-side">
                <div class="oa-panel">
                    <div class="oa-panel-h"><strong><i class="bx bx-show"></i> Live preview</strong><small>Approximate</small></div>
                    <div class="oa-gpt">
                        <div class="oa-gpt-top"><i class="bx bx-bot"></i> ChatGPT</div>
                        <div class="oa-msg-me">Where can I get good bread delivered?</div>
                        <div class="oa-msg-ai">
                            <span class="av"><i class="bx bx-bot"></i></span>
                            <div style="flex:1">Here are a few great options near you:<div class="ln" style="width:92%"></div><div class="ln" style="width:70%"></div></div>
                        </div>
                        <div class="oa-sponsor">SPONSORED</div>
                        <div class="oa-adcard">
                            <div class="img" id="pvImg"><i class="bx bx-image"></i></div>
                            <div class="txt">
                                <div class="host"><i class="bx bx-globe"></i> <span id="pvHost">example.com</span></div>
                                <strong id="pvTitle" class="ph">Your headline</strong>
                                <p id="pvBody" class="ph">Your description appears here.</p>
                            </div>
                        </div>
                        <div class="oa-gpt-input">Ask anything <span><i class="bx bx-up-arrow-alt"></i></span></div>
                    </div>
                </div>

                <div class="oa-panel">
                    <div class="oa-panel-h"><strong><i class="bx bx-receipt"></i> Summary</strong></div>
                    <div class="oa-sum">
                        <div class="oa-sum-row"><span>Budget</span><strong id="sumBudget">—</strong></div>
                        <div class="oa-sum-row"><span>Schedule</span><strong id="sumSchedule">Starts once approved</strong></div>
                        <div class="oa-sum-row"><span>Goal</span><strong id="sumGoal">Website visits</strong></div>
                        <div class="oa-sum-row"><span>Max bid</span><strong id="sumBid">—</strong></div>
                    </div>
                    <div class="oa-ready">
                        <div class="oa-ready-top">Ready to launch <span id="readyPct">0%</span></div>
                        <div class="oa-ready-bar"><i id="readyBar"></i></div>
                        <ul id="readyList">
                            <li data-ready="name"><i class="bx bxs-check-circle"></i> Name</li>
                            <li data-ready="budget"><i class="bx bxs-check-circle"></i> Budget</li>
                            <li data-ready="max_bid"><i class="bx bxs-check-circle"></i> Max bid</li>
                            <li data-ready="title"><i class="bx bxs-check-circle"></i> Headline</li>
                            <li data-ready="target_link"><i class="bx bxs-check-circle"></i> Landing page</li>
                            <li data-ready="image"><i class="bx bxs-check-circle"></i> Image</li>
                        </ul>
                    </div>
                </div>

                <div class="oa-note"><i class="bx bx-info-circle"></i><span>OpenAI reviews every ad, usually within a day. ChatGPT Ads currently serve in the US, UK, Europe, Mexico, Brazil, Japan and South Korea.</span></div>
            </aside>
        </div>

        <div class="oa-bar">
            <div class="oa-bar-txt"><strong id="barTitle">New campaign</strong><small id="barSub">Fill in the steps to launch</small></div>
            <a href="{{ $indexUrl }}" class="cancel">Cancel</a>
            <button type="submit" class="go" id="oaSubmit"><i class="bx bx-rocket"></i> Create campaign</button>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Deferred to `load` like the other builders: the layout's Vue root
    // re-renders #app once its module script runs, replacing these nodes.
    window.addEventListener('load', function () {
        const form = document.getElementById('oaForm');
        if (!form) return;
        const $ = (id) => document.getElementById(id);
        const val = (id) => ($(id).value || '').trim();
        const alertBox = $('oaAlert');
        const money = (v) => '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const date = (v) => new Date(v + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
        const validUrl = (v) => { try { const u = new URL(v); return /^https?:$/.test(u.protocol) && u.hostname.includes('.'); } catch (e) { return false; } };

        // ---- preview -------------------------------------------------------
        function bindText(id, max, preview, fallback) {
            const el = $(id), counter = document.querySelector('[data-count="' + id + '"]');
            el.addEventListener('input', () => {
                const n = el.value.length;
                counter.textContent = n + '/' + max;
                counter.classList.toggle('near', n >= max * .85 && n <= max);
                counter.classList.toggle('over', n > max);
                $(preview).textContent = el.value.trim() || fallback;
                $(preview).classList.toggle('ph', !el.value.trim());
            });
        }
        bindText('title', 50, 'pvTitle', 'Your headline');
        bindText('body', 100, 'pvBody', 'Your description appears here.');
        $('target_link').addEventListener('input', function () {
            $('pvHost').textContent = validUrl(this.value) ? new URL(this.value).hostname.replace(/^www\./, '') : 'example.com';
        });

        // ---- image ---------------------------------------------------------
        const input = $('image'), drop = $('oaDrop');
        function showFile(file) {
            if (!file) {
                $('oaFile').classList.remove('show');
                drop.style.display = '';
                $('pvImg').innerHTML = '<i class="bx bx-image"></i>';
                return refresh();
            }
            const url = URL.createObjectURL(file);
            $('oaFileImg').src = url;
            $('oaFileName').textContent = file.name;
            $('oaFileSize').textContent = (file.size / 1048576).toFixed(2) + ' MB' + (file.size > 5 * 1048576 ? ' · too large (max 5 MB)' : '')
                + (/^image\/(jpeg|png|webp)$/.test(file.type) ? '' : ' · use a JPG, PNG or WebP');
            // MediaInputAttach renders its hidden media_asset_ids[] right after this change event.
            setTimeout(() => {
                if (form.querySelector('input[name="media_asset_ids[]"]')) $('oaFileSize').insertAdjacentHTML('beforeend', ' · <b>From Media Gallery</b>');
            }, 50);
            $('oaFile').classList.add('show');
            drop.style.display = 'none';
            const img = document.createElement('img');
            img.src = url;
            $('pvImg').replaceChildren(img);
            refresh();
        }
        drop.addEventListener('click', () => input.click());
        drop.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.add('drag'); }));
        ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, (e) => { e.preventDefault(); drop.classList.remove('drag'); }));
        drop.addEventListener('drop', (e) => {
            const file = e.dataTransfer.files[0];
            if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return;
            const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
            showFile(file);
        });
        input.addEventListener('change', () => showFile(input.files[0]));
        $('oaFileRemove').addEventListener('click', () => { input.value = ''; input.dispatchEvent(new Event('change', { bubbles: true })); });

        // ---- summary, checklist, step state -------------------------------
        $('billing_event').addEventListener('change', function () {
            $('bidUnit').textContent = this.value === 'click' ? 'per click' : 'per 1,000';
        });

        function refresh() {
            const ok = {
                name: val('name').length >= 3,
                budget: Number(val('budget')) >= 1,
                max_bid: Number(val('max_bid')) >= 0.01,
                title: val('title').length >= 3 && val('title').length <= 50,
                target_link: validUrl(val('target_link')),
                image: input.files.length > 0,
            };
            const done = Object.values(ok).filter(Boolean).length;
            const pct = Math.round(done / 6 * 100);
            $('readyPct').textContent = pct + '%';
            $('readyBar').style.width = pct + '%';
            Object.entries(ok).forEach(([k, v]) => document.querySelector('[data-ready="' + k + '"]').classList.toggle('ok', v));

            const sections = { campaign: ok.name && ok.budget, bidding: ok.max_bid, ad: ok.title && ok.target_link && ok.image, launch: done === 6 };
            Object.entries(sections).forEach(([k, v]) => {
                const badge = document.querySelector('[data-check="' + k + '"]');
                badge.classList.toggle('ok', v);
                badge.querySelector('span').textContent = v ? 'Complete' : 'Required';
                document.querySelector('[data-step="' + k + '"]').classList.toggle('done', v);
            });
            document.querySelector('[data-check="launch"] span').textContent = done === 6 ? 'Ready' : 'Choose';

            const start = val('start_time'), end = val('end_time');
            const isClick = $('billing_event').value === 'click';
            $('sumBudget').textContent = ok.budget ? money(val('budget')) + ' lifetime' : '—';
            $('sumSchedule').textContent = (start ? date(start) : 'Once approved') + ' → ' + (end ? date(end) : 'Budget spent');
            $('sumGoal').textContent = form.querySelector('[name="bidding_type"]:checked').value === 'clicks' ? 'Website visits' : 'Awareness';
            $('sumBid').textContent = ok.max_bid ? money(val('max_bid')) + (isClick ? ' / click' : ' / 1,000 impr.') : '—';
            $('barTitle').textContent = val('name') || 'New campaign';
            $('barSub').textContent = done === 6
                ? 'All set · ' + (form.querySelector('[name="launch"]:checked').value === 'active' ? 'goes live after approval' : 'saved as paused')
                : (6 - done) + ' item' + (6 - done === 1 ? '' : 's') + ' left to fill in';
        }
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        refresh();

        // highlight the section in view
        const links = document.querySelectorAll('#oaSteps a');
        const io = new IntersectionObserver((entries) => {
            entries.forEach((en) => {
                if (!en.isIntersecting) return;
                links.forEach((a) => a.classList.toggle('current', a.getAttribute('href') === '#' + en.target.id));
            });
        }, { rootMargin: '-40% 0px -55% 0px' });
        document.querySelectorAll('.oa-sec').forEach((s) => io.observe(s));
        links.forEach((a) => a.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelector(a.getAttribute('href')).scrollIntoView({ behavior: 'smooth', block: 'start' });
        }));

        // ---- submit ---------------------------------------------------------
        function showAlert(type, text) {
            alertBox.className = 'oa-alert show ' + type;
            alertBox.innerHTML = '<i class="bx ' + (type === 'ok' ? 'bx-check-circle' : 'bx-error-circle') + '"></i>';
            alertBox.append(document.createTextNode(text));
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            document.querySelectorAll('.oa-err').forEach((el) => el.textContent = '');
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            alertBox.className = 'oa-alert';
            const btn = $('oaSubmit');
            btn.disabled = true;
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Creating…';

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));

                if (res.status === 422 && data.errors) {
                    let first = null;
                    Object.entries(data.errors).forEach(([field, msgs]) => {
                        const el = document.querySelector('[data-err="' + field + '"]');
                        if (el) el.textContent = msgs[0];
                        const control = form.querySelector('[name="' + field + '"]');
                        if (control && control.type !== 'radio' && control.type !== 'file') control.classList.add('is-invalid');
                        first = first || el;
                    });
                    showAlert('err', 'Please fix the highlighted fields.');
                    (first || alertBox).scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (res.ok && data.success) {
                    showAlert('ok', data.message || 'Campaign created.');
                    btn.innerHTML = '<i class="bx bx-check"></i> Created';
                    window.location.href = data.redirect_url || @json($indexUrl);
                    return;
                } else {
                    showAlert('err', data.message || data.error || 'Could not create the campaign. Please try again.');
                    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } catch (err) {
                showAlert('err', 'Network error. Please check your connection and try again.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-rocket"></i> Create campaign';
        });
    });
</script>
@endpush
