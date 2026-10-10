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
    .oa-wrap { max-width: 1180px; margin: 0 auto; }
    .oa-head { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
    .oa-head .logo { width: 46px; height: 46px; border-radius: 14px; background: #111827; color: #fff; display: grid; place-items: center; font-size: 24px; }
    .oa-head h4 { margin: 0; font-weight: 700; color: #111827; }
    .oa-head p { margin: 2px 0 0; color: #6b7280; font-size: .88rem; }
    .oa-head .back { margin-left: auto; }
    .oa-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 22px; align-items: start; }
    .oa-card { background: #fff; border: 1px solid #e7e9f0; border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); margin-bottom: 18px; }
    .oa-card-h { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #eef0f4; }
    .oa-card-h .n { width: 26px; height: 26px; border-radius: 50%; background: #111827; color: #fff; font-size: .78rem; font-weight: 700; display: grid; place-items: center; }
    .oa-card-h strong { color: #111827; }
    .oa-card-h small { color: #6b7280; margin-left: auto; }
    .oa-card-b { padding: 18px; }
    .oa-card-b .form-label { font-weight: 600; font-size: .84rem; color: #374151; }
    .oa-hint { font-size: .76rem; color: #6b7280; margin-top: 4px; }
    .oa-count { float: right; font-weight: 500; color: #9ca3af; }
    .oa-count.over { color: #dc2626; }
    .oa-seg { display: flex; gap: 8px; flex-wrap: wrap; }
    .oa-seg label { flex: 1; min-width: 150px; border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px 12px; cursor: pointer; display: flex; gap: 10px; align-items: flex-start; }
    .oa-seg input { margin-top: 3px; }
    .oa-seg label:has(input:checked) { border-color: #111827; background: #f9fafb; }
    .oa-seg span strong { display: block; font-size: .86rem; color: #111827; }
    .oa-seg span small { color: #6b7280; font-size: .76rem; }
    .oa-drop { border: 1.5px dashed #d1d5db; border-radius: 14px; padding: 18px; text-align: center; cursor: pointer; color: #6b7280; }
    .oa-drop:hover { border-color: #111827; }
    .oa-drop i { font-size: 28px; display: block; margin-bottom: 4px; }
    .oa-err { color: #dc2626; font-size: .78rem; margin-top: 4px; }
    .oa-alert { border-radius: 12px; padding: 12px 14px; font-size: .86rem; margin-bottom: 16px; display: none; }
    .oa-alert.show { display: block; }
    .oa-alert.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .oa-alert.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .oa-side { position: sticky; top: 90px; }
    .oa-chat { background: #f7f7f8; border-radius: 16px; padding: 16px; }
    .oa-bubble { background: #fff; border-radius: 14px; padding: 10px 12px; font-size: .82rem; color: #374151; margin-bottom: 10px; }
    .oa-bubble.me { background: #e9e9eb; margin-left: 40px; }
    .oa-sponsor { font-size: .7rem; color: #6b7280; margin: 4px 2px 6px; display: flex; align-items: center; gap: 4px; }
    .oa-adcard { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; }
    .oa-adcard .img { aspect-ratio: 1.91 / 1; background: #eef0f4; display: grid; place-items: center; color: #9ca3af; }
    .oa-adcard .img img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .oa-adcard .txt { padding: 10px 12px; }
    .oa-adcard .txt strong { display: block; font-size: .9rem; color: #111827; word-break: break-word; }
    .oa-adcard .txt p { margin: 3px 0 6px; font-size: .8rem; color: #4b5563; word-break: break-word; }
    .oa-adcard .txt small { color: #6b7280; font-size: .74rem; }
    .oa-note { font-size: .78rem; color: #6b7280; margin-top: 12px; display: flex; gap: 6px; }
    .oa-submit { display: flex; gap: 10px; justify-content: flex-end; }
    .oa-submit .btn-dark { min-width: 180px; }
    @media (max-width: 991.98px) { .oa-grid { grid-template-columns: 1fr; } .oa-side { position: static; } }
</style>
@endpush

@section('content')
<div class="oa-wrap">
    <div class="oa-head">
        <span class="logo"><i class="bx bx-bot"></i></span>
        <div>
            <h4>New ChatGPT Ads campaign</h4>
            <p>Campaign, ad group and a chat-card ad in one go. OpenAI reviews each ad before it runs.</p>
        </div>
        <a href="{{ route('admin.ads.campaigns.index', ['platform' => 'openai']) }}" class="btn btn-outline-secondary btn-sm back"><i class="bx bx-arrow-back"></i> Campaigns</a>
    </div>

    @include('admin.ads.partials.account-switcher')

    @if (! $account)
        <div class="oa-card"><div class="oa-card-b text-center py-5">
            <i class="bx bx-key" style="font-size:36px;color:#9ca3af"></i>
            <h5 class="mt-2">Connect ChatGPT Ads first</h5>
            <p class="text-muted mb-3">Add your OpenAI Ads API key in Connections, then come back here.</p>
            <a href="{{ \App\Support\Connections\HubLink::for('openai') ?? route('admin.connections.index') }}" class="btn btn-dark">Connect ChatGPT Ads</a>
        </div></div>
    @else
    <form id="oaForm" action="{{ route('admin.ads.campaigns.store', ['platform' => 'openai']) }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf
        <div id="oaAlert" class="oa-alert"></div>

        <div class="oa-grid">
            <div>
                <div class="oa-card">
                    <div class="oa-card-h"><span class="n">1</span><strong>Campaign</strong><small>Budget and schedule</small></div>
                    <div class="oa-card-b row g-3">
                        <div class="col-12">
                            <label class="form-label" for="name">Campaign name</label>
                            <input class="form-control" id="name" name="name" maxlength="1000" placeholder="e.g. Spring sale – ChatGPT" required>
                            <div class="oa-err" data-err="name"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="budget">Lifetime budget (USD)</label>
                            <input class="form-control" type="number" id="budget" name="budget" min="1" step="0.01" placeholder="500" required>
                            <div class="oa-hint">Total spend limit for the campaign.</div>
                            <div class="oa-err" data-err="budget"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="start_time">Start date</label>
                            <input class="form-control" type="date" id="start_time" name="start_time" min="{{ now()->toDateString() }}">
                            <div class="oa-hint">Empty = start after approval.</div>
                            <div class="oa-err" data-err="start_time"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="end_time">End date</label>
                            <input class="form-control" type="date" id="end_time" name="end_time" min="{{ now()->toDateString() }}">
                            <div class="oa-hint">Optional.</div>
                            <div class="oa-err" data-err="end_time"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Optimise for</label>
                            <div class="oa-seg">
                                <label><input type="radio" name="bidding_type" value="clicks" checked><span><strong>Clicks</strong><small>Visits to your site</small></span></label>
                                <label><input type="radio" name="bidding_type" value="impressions"><span><strong>Impressions</strong><small>Reach and awareness</small></span></label>
                            </div>
                            <div class="oa-err" data-err="bidding_type"></div>
                        </div>
                    </div>
                </div>

                <div class="oa-card">
                    <div class="oa-card-h"><span class="n">2</span><strong>Ad group</strong><small>Bidding</small></div>
                    <div class="oa-card-b row g-3">
                        <div class="col-md-12">
                            <label class="form-label" for="ad_group_name">Ad group name</label>
                            <input class="form-control" id="ad_group_name" name="ad_group_name" placeholder="Defaults to the campaign name">
                            <div class="oa-err" data-err="ad_group_name"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="billing_event">Pay per</label>
                            <select class="form-control" id="billing_event" name="billing_event">
                                <option value="click">Click</option>
                                <option value="impression">Impression</option>
                            </select>
                            <div class="oa-err" data-err="billing_event"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="max_bid">Max bid (USD)</label>
                            <input class="form-control" type="number" id="max_bid" name="max_bid" min="0.01" step="0.01" placeholder="1.50">
                            <div class="oa-hint" id="bidHint">Most you'll pay per click.</div>
                            <div class="oa-err" data-err="max_bid"></div>
                        </div>
                    </div>
                </div>

                <div class="oa-card">
                    <div class="oa-card-h"><span class="n">3</span><strong>Chat-card ad</strong><small>Shown below ChatGPT answers</small></div>
                    <div class="oa-card-b row g-3">
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
                            <label class="form-label" for="target_link">Landing page URL</label>
                            <input class="form-control" type="url" id="target_link" name="target_link" placeholder="https://example.com/offer" required>
                            <div class="oa-err" data-err="target_link"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Image</label>
                            <div class="oa-drop" id="oaDrop">
                                <i class="bx bx-image-add"></i>
                                <span id="oaDropText">Click to upload a JPG, PNG or WebP (max 5 MB)</span>
                            </div>
                            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" hidden>
                            <div class="oa-err" data-err="image"></div>
                        </div>
                    </div>
                </div>

                <div class="oa-card">
                    <div class="oa-card-h"><span class="n">4</span><strong>Launch</strong></div>
                    <div class="oa-card-b">
                        <div class="oa-seg mb-3">
                            <label><input type="radio" name="launch" value="active" checked><span><strong>Run after approval</strong><small>Starts once OpenAI approves the ad</small></span></label>
                            <label><input type="radio" name="launch" value="paused"><span><strong>Save paused</strong><small>Activate it later from Campaigns</small></span></label>
                        </div>
                        <div class="oa-submit">
                            <a href="{{ route('admin.ads.campaigns.index', ['platform' => 'openai']) }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-dark" id="oaSubmit"><i class="bx bx-rocket"></i> Create campaign</button>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="oa-side">
                <div class="oa-card">
                    <div class="oa-card-h"><strong>Preview</strong><small>Approximate</small></div>
                    <div class="oa-card-b">
                        <div class="oa-chat">
                            <div class="oa-bubble me">Where can I get good bread delivered?</div>
                            <div class="oa-bubble">Here are a few options near you…</div>
                            <div class="oa-sponsor"><i class="bx bx-info-circle"></i> Sponsored</div>
                            <div class="oa-adcard">
                                <div class="img" id="pvImg"><i class="bx bx-image" style="font-size:32px"></i></div>
                                <div class="txt">
                                    <strong id="pvTitle">Your headline</strong>
                                    <p id="pvBody">Your description appears here.</p>
                                    <small id="pvHost">example.com</small>
                                </div>
                            </div>
                        </div>
                        <div class="oa-note"><i class="bx bx-shield-quarter"></i><span>Ads are reviewed by OpenAI and only appear in supported countries.</span></div>
                    </div>
                </div>
            </aside>
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
        const alertBox = $('oaAlert');

        function bindCount(id, max, preview, fallback) {
            const el = $(id), counter = document.querySelector('[data-count="' + id + '"]');
            const sync = () => {
                counter.textContent = el.value.length + '/' + max;
                counter.classList.toggle('over', el.value.length > max);
                $(preview).textContent = el.value.trim() || fallback;
            };
            el.addEventListener('input', sync);
            sync();
        }
        bindCount('title', 50, 'pvTitle', 'Your headline');
        bindCount('body', 100, 'pvBody', 'Your description appears here.');

        $('target_link').addEventListener('input', function () {
            try { $('pvHost').textContent = new URL(this.value).hostname.replace(/^www\./, ''); }
            catch (e) { $('pvHost').textContent = 'example.com'; }
        });

        $('billing_event').addEventListener('change', function () {
            $('bidHint').textContent = this.value === 'click' ? "Most you'll pay per click." : "Most you'll pay per 1,000 impressions.";
        });

        $('oaDrop').addEventListener('click', () => $('image').click());
        $('image').addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            $('oaDropText').textContent = file.name;
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            $('pvImg').replaceChildren(img);
        });

        function showAlert(type, html) {
            alertBox.className = 'oa-alert show ' + type;
            alertBox.innerHTML = html;
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            document.querySelectorAll('.oa-err').forEach((el) => el.textContent = '');
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
                    Object.entries(data.errors).forEach(([field, msgs]) => {
                        const el = document.querySelector('[data-err="' + field + '"]');
                        if (el) el.textContent = msgs[0];
                    });
                    showAlert('err', 'Please fix the highlighted fields.');
                } else if (res.ok && data.success) {
                    showAlert('ok', esc(data.message || 'Campaign created.'));
                    window.location.href = data.redirect_url || @json(route('admin.ads.campaigns.index', ['platform' => 'openai']));
                    return;
                } else {
                    showAlert('err', esc(data.message || data.error || 'Could not create the campaign. Please try again.'));
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
