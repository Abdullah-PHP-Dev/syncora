@extends('layouts.app')

@section('title', 'Business Profile')

@php
    $days = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
    $hours = $profile->business_hours ?? [];
    $closedDays = $hours['closed'] ?? [];

    // Saved hours are free text ("9:00-18:00", "9:00 AM - 6:00 PM") - parse
    // them into 24h values for the time pickers. A value this can't parse
    // (hand-typed before the pickers existed, eg. "by appointment") is
    // kept in a plain text field instead of being silently dropped.
    $parseRange = function (?string $value): ?array {
        if (!$value || !preg_match('/^\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*(?:-|–|to)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*$/i', $value, $m)) {
            return null;
        }
        $to24 = function ($h, $min, $ap) {
            $h = (int) $h;
            $ap = strtolower((string) $ap);
            if ($ap === 'pm' && $h < 12) $h += 12;
            if ($ap === 'am' && $h === 12) $h = 0;
            return $h <= 23 ? sprintf('%02d:%02d', $h, (int) ($min ?: 0)) : null;
        };
        $from = $to24($m[1], $m[2] ?? 0, $m[3] ?? '');
        $to = $to24($m[4], $m[5] ?? 0, $m[6] ?? '');
        return $from && $to ? [$from, $to] : null;
    };

    // Policies are stored Purifier-cleaned (auto-wrapped in <p>) - show
    // them as plain text with real line breaks in the textarea.
    $plain = fn (?string $html) => $html === null ? '' : trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    $dayRows = [];
    foreach ($days as $key => $label) {
        $raw = $hours[$key] ?? '';
        $range = $parseRange($raw);
        $dayRows[$key] = [
            'label'  => $label,
            'closed' => in_array($key, $closedDays, true),
            'from'   => $range[0] ?? '',
            'to'     => $range[1] ?? '',
            'legacy' => $raw !== '' && !$range ? $raw : null,
        ];
    }
@endphp

@push('styles')
<style>
    .bp { --ln: #e6e8ef; --ln-soft: #f0f2f6; --ink: #1b2130; --ink2: #5b6475; --muted: #8a93a3; --brand: #6d4aff; --brand-soft: #f1edff; --ok: #079455; --radius: 14px; }

    .bp-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; height: 40px; padding: 0 1rem; border-radius: 10px; font-size: .86rem; font-weight: 600; border: 1px solid transparent; cursor: pointer; white-space: nowrap; text-decoration: none; transition: background .15s, border-color .15s, color .15s; }
    .bp-btn i { font-size: 1.05rem; }
    .bp-btn:disabled { opacity: .6; cursor: not-allowed; }
    .bp-btn-light { background: #fff; color: var(--ink); box-shadow: 0 1px 2px rgba(16, 24, 40, .08); }
    .bp-btn-light:hover { background: #f8f9fc; color: var(--ink); }
    .bp-btn-brand { background: var(--brand); color: #fff; }
    .bp-btn-brand:hover:not(:disabled) { background: #5a36f0; color: #fff; }
    .bp-btn-outline { background: #fff; border-color: var(--ln); color: var(--ink2); }
    .bp-btn-outline:hover { border-color: #cfd4de; color: var(--ink); }
    .bp-link { background: none; border: 0; padding: 0; color: var(--brand); font-size: .8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: .25rem; text-decoration: none; }
    .bp-link:hover { text-decoration: underline; color: var(--brand); }

    /* Hero */
    .bp-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem 1.25rem; padding: 1.5rem; border-radius: 18px; margin-bottom: 1.25rem; color: #fff; background: linear-gradient(110deg, #6d4aff 0%, #5b7cfa 55%, #16a3b8 100%); }
    .bp-hero-id { display: flex; align-items: center; gap: 1rem; flex: 1 1 340px; min-width: 0; }
    .bp-hero-mark { width: 60px; height: 60px; border-radius: 50%; background: rgba(255, 255, 255, .2); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
    .bp-hero h4 { color: #fff; font-weight: 700; font-size: 1.35rem; margin: 0 0 .3rem; }
    .bp-hero p { color: rgba(255, 255, 255, .92); margin: 0; font-size: .9rem; max-width: 640px; }
    .bp-hero-actions { display: flex; flex-wrap: wrap; gap: .5rem; }

    /* Cards */
    .bp-card { background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); margin-bottom: 1.25rem; }
    .bp-card-h { display: flex; align-items: center; gap: .85rem; padding: 1.1rem 1.35rem; border-bottom: 1px solid var(--ln-soft); }
    .bp-card-h .ic { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
    .bp-card-h h5 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--ink); }
    .bp-card-h p { margin: .1rem 0 0; font-size: .78rem; color: var(--muted); }
    .bp-card-h .end { margin-left: auto; }
    .bp-card-b { padding: 1.25rem 1.35rem; }
    .ic-violet { background: #efeaff; color: var(--brand); }
    .ic-blue { background: #e4ecff; color: #2e5bff; }
    .ic-green { background: #dcf5e7; color: var(--ok); }
    .ic-amber { background: #fdebd0; color: #dc6803; }

    /* Fields */
    .bp-label { display: block; font-size: .8rem; font-weight: 600; color: var(--ink); margin-bottom: .35rem; }
    .bp-label small { font-weight: 400; color: var(--muted); }
    .bp-field { position: relative; }
    .bp-field > i { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1.1rem; pointer-events: none; }
    .bp-field .form-control { padding-left: 2.35rem; }
    .bp .form-control { height: 42px; border-color: var(--ln); border-radius: 10px; font-size: .88rem; }
    .bp textarea.form-control { height: auto; min-height: 120px; padding: .7rem .85rem; line-height: 1.55; resize: vertical; }
    .bp .form-control:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(109, 74, 255, .12); }
    .bp-hint { display: flex; justify-content: space-between; gap: 1rem; font-size: .74rem; color: var(--muted); margin-top: .3rem; }

    /* Hours */
    .bp-days { display: flex; flex-direction: column; }
    .bp-day { display: grid; grid-template-columns: 130px 120px minmax(0, 1fr); align-items: center; gap: 1rem; padding: .7rem 0; border-bottom: 1px dashed var(--ln-soft); }
    .bp-day:last-child { border-bottom: 0; }
    .bp-day-name { font-weight: 600; color: var(--ink); font-size: .88rem; }
    .bp-day-times { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
    .bp-day-times .form-control { width: 140px; height: 38px; }
    .bp-day-times .to { font-size: .8rem; color: var(--muted); }
    .bp-day-times .legacy { width: 100%; max-width: 310px; }
    .bp-closed-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .8rem; border-radius: 8px; background: #f2f4f7; color: var(--ink2); font-size: .8rem; font-weight: 600; }
    .bp-day.is-closed .bp-day-name { color: var(--muted); }

    .bp-switch { display: inline-flex; align-items: center; gap: .55rem; cursor: pointer; margin: 0; user-select: none; }
    .bp-switch input { position: absolute; opacity: 0; pointer-events: none; }
    .bp-switch .track { position: relative; width: 38px; height: 22px; border-radius: 22px; background: #d0d5dd; flex-shrink: 0; transition: background .15s; }
    .bp-switch .track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s; box-shadow: 0 1px 2px rgba(0, 0, 0, .2); }
    .bp-switch input:checked + .track { background: #12b76a; }
    .bp-switch input:checked + .track::after { transform: translateX(16px); }
    .bp-switch input:focus-visible + .track { box-shadow: 0 0 0 3px rgba(109, 74, 255, .25); }
    .bp-switch .txt { font-size: .82rem; font-weight: 600; color: var(--ink2); min-width: 44px; }

    /* Sidebar */
    .bp-side { position: sticky; top: 90px; }
    .bp-strength { display: flex; align-items: center; gap: 1rem; }
    .bp-ring { --p: 0; width: 76px; height: 76px; border-radius: 50%; flex-shrink: 0; display: grid; place-items: center; background: conic-gradient(var(--brand) calc(var(--p) * 1%), #eceff4 0); transition: --p .3s; }
    .bp-ring span { width: 60px; height: 60px; border-radius: 50%; background: #fff; display: grid; place-items: center; font-weight: 700; font-size: 1.05rem; color: var(--ink); }
    .bp-strength strong { display: block; color: var(--ink); font-size: .95rem; }
    .bp-strength small { color: var(--muted); font-size: .78rem; }
    .bp-check { list-style: none; padding: 0; margin: 1.1rem 0 0; }
    .bp-check li { display: flex; align-items: center; gap: .55rem; padding: .42rem 0; font-size: .83rem; color: var(--ink2); }
    .bp-check li i { font-size: 1.15rem; color: #c9cedb; }
    .bp-check li.done { color: var(--ink); }
    .bp-check li.done i { color: var(--ok); }
    .bp-qs { list-style: none; padding: 0; margin: 0; }
    .bp-qs li { display: flex; gap: .55rem; padding: .5rem 0; font-size: .8rem; color: var(--muted); border-bottom: 1px solid var(--ln-soft); }
    .bp-qs li:last-child { border-bottom: 0; }
    .bp-qs li i { font-size: 1rem; margin-top: .05rem; flex-shrink: 0; }
    .bp-qs li.on { color: var(--ink); }
    .bp-qs li.on i { color: var(--brand); }
    .bp-note { display: flex; gap: .6rem; padding: .85rem; border-radius: 12px; background: var(--brand-soft); color: var(--ink2); font-size: .78rem; line-height: 1.5; }
    .bp-note i { color: var(--brand); font-size: 1.15rem; flex-shrink: 0; }

    /* Save bar */
    .bp-savebar { position: sticky; bottom: 1rem; z-index: 20; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem .8rem 1.25rem; background: #fff; border: 1px solid var(--ln); border-radius: var(--radius); box-shadow: 0 10px 30px rgba(16, 24, 40, .1); }
    .bp-status { display: flex; align-items: center; gap: .5rem; font-size: .84rem; color: var(--muted); }
    .bp-status .dot { width: 8px; height: 8px; border-radius: 50%; background: #12b76a; }
    .bp-savebar.dirty .bp-status { color: #b54708; }
    .bp-savebar.dirty .bp-status .dot { background: #f79009; }
    .bp-savebar .actions { display: flex; gap: .5rem; }

    @media (max-width: 767.98px) {
        .bp-day { grid-template-columns: 1fr auto; gap: .5rem; }
        .bp-day-times { grid-column: 1 / -1; }
        .bp-day-times .form-control { width: calc(50% - 1.2rem); }
        .bp-savebar { flex-direction: column; align-items: stretch; }
        .bp-savebar .actions .bp-btn { flex: 1; }
    }

    [dir="rtl"] .bp-field > i { left: auto; right: .8rem; }
    [dir="rtl"] .bp-field .form-control { padding-left: .75rem; padding-right: 2.35rem; }
    [dir="rtl"] .bp-card-h .end { margin-left: 0; margin-right: auto; }
</style>
@endpush

@section('content')
<div class="bp">

    <div class="bp-hero">
        <div class="bp-hero-id">
            <span class="bp-hero-mark"><i class="bx bx-store-alt"></i></span>
            <div>
                <h4>Business Profile</h4>
                <p>The facts your AI Copilot checks before answering customers. Saving keeps matching entries in your Knowledge Base up to date automatically.</p>
            </div>
        </div>
        <div class="bp-hero-actions">
            <a href="{{ route('admin.knowledge-base.index') }}" class="bp-btn bp-btn-light"><i class="bx bx-book-open"></i> View Knowledge Base</a>
        </div>
    </div>

    <form id="business-profile-form" novalidate>
        @csrf

        <div class="row g-4">
            <div class="col-xl-8">

                {{-- Business details --}}
                <div class="bp-card">
                    <div class="bp-card-h">
                        <span class="ic ic-violet"><i class="bx bx-id-card"></i></span>
                        <div>
                            <h5>Business Details</h5>
                            <p>How customers can identify and reach you.</p>
                        </div>
                    </div>
                    <div class="bp-card-b">
                        <div class="mb-3">
                            <label class="bp-label" for="business_name">Business name</label>
                            <div class="bp-field">
                                <i class="bx bx-buildings"></i>
                                <input type="text" class="form-control" id="business_name" name="business_name" maxlength="150" placeholder="e.g. Al-Noor Electronics" value="{{ $profile->business_name }}">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="bp-label" for="phone">Phone number</label>
                                <div class="bp-field">
                                    <i class="bx bx-phone"></i>
                                    <input type="tel" class="form-control" id="phone" name="phone" maxlength="30" placeholder="+966 5X XXX XXXX" value="{{ $profile->phone }}">
                                </div>
                            </div>
                            <div class="col-md-7">
                                <label class="bp-label" for="address">Address</label>
                                <div class="bp-field">
                                    <i class="bx bx-map"></i>
                                    <input type="text" class="form-control" id="address" name="address" maxlength="255" placeholder="Street, district, city" value="{{ $profile->address }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Business hours --}}
                <div class="bp-card">
                    <div class="bp-card-h">
                        <span class="ic ic-blue"><i class="bx bx-time-five"></i></span>
                        <div>
                            <h5>Business Hours</h5>
                            <p>Switch a day off if you're closed. Leave the times empty if they're unknown.</p>
                        </div>
                        <button type="button" class="bp-link end" id="copy-monday"><i class="bx bx-copy"></i> Copy Monday to all</button>
                    </div>
                    <div class="bp-card-b py-2">
                        <div class="bp-days">
                            @foreach ($dayRows as $key => $day)
                                <div class="bp-day {{ $day['closed'] ? 'is-closed' : '' }}" data-day="{{ $key }}">
                                    <div class="bp-day-name">{{ $day['label'] }}</div>
                                    <label class="bp-switch">
                                        <input type="checkbox" class="open-toggle" {{ $day['closed'] ? '' : 'checked' }} aria-label="{{ $day['label'] }} open">
                                        <span class="track"></span>
                                        <span class="txt">{{ $day['closed'] ? 'Closed' : 'Open' }}</span>
                                    </label>
                                    <div class="bp-day-times">
                                        <div class="times-open" @if ($day['closed']) hidden @endif>
                                            @if ($day['legacy'])
                                                <input type="text" class="form-control legacy" maxlength="50" value="{{ $day['legacy'] }}" aria-label="{{ $day['label'] }} hours">
                                            @else
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <input type="time" class="form-control t-from" value="{{ $day['from'] }}" aria-label="{{ $day['label'] }} opening time">
                                                    <span class="to">to</span>
                                                    <input type="time" class="form-control t-to" value="{{ $day['to'] }}" aria-label="{{ $day['label'] }} closing time">
                                                </div>
                                            @endif
                                        </div>
                                        <span class="bp-closed-pill times-closed" @if (!$day['closed']) hidden @endif><i class="bx bx-moon"></i> Closed all day</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Policies --}}
                <div class="bp-card">
                    <div class="bp-card-h">
                        <span class="ic ic-green"><i class="bx bx-shield-quarter"></i></span>
                        <div>
                            <h5>Policies</h5>
                            <p>Write these the way you'd explain them to a customer.</p>
                        </div>
                    </div>
                    <div class="bp-card-b">
                        <div class="mb-4">
                            <label class="bp-label" for="delivery_policy">Delivery policy</label>
                            <textarea class="form-control" id="delivery_policy" name="delivery_policy" rows="4" maxlength="5000" data-counter="delivery_count" placeholder="e.g. We deliver across Saudi Arabia. Riyadh orders arrive in 1-2 business days, other cities in 3-5 days. Delivery is free over 200 SAR.">{{ $plain($profile->delivery_policy) }}</textarea>
                            <div class="bp-hint"><span>Cities, delivery times and fees.</span><span id="delivery_count">0 / 5000</span></div>
                        </div>
                        <div>
                            <label class="bp-label" for="return_policy">Return &amp; refund policy</label>
                            <textarea class="form-control" id="return_policy" name="return_policy" rows="4" maxlength="5000" data-counter="return_count" placeholder="e.g. Unused items can be returned within 14 days of delivery. Refunds go back to the original payment method within 5-7 business days.">{{ $plain($profile->return_policy) }}</textarea>
                            <div class="bp-hint"><span>Time limits, conditions and how refunds are paid.</span><span id="return_count">0 / 5000</span></div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-xl-4">
                <div class="bp-side">

                    <div class="bp-card">
                        <div class="bp-card-b">
                            <div class="bp-strength">
                                <div class="bp-ring" id="strength-ring"><span id="strength-pct">0%</span></div>
                                <div>
                                    <strong>Profile strength</strong>
                                    <small id="strength-text">Complete your profile so the AI Copilot can answer more questions.</small>
                                </div>
                            </div>
                            <ul class="bp-check" id="strength-list">
                                <li data-check="business_name"><i class="bx bxs-check-circle"></i> Business name</li>
                                <li data-check="hours"><i class="bx bxs-check-circle"></i> Business hours</li>
                                <li data-check="phone"><i class="bx bxs-check-circle"></i> Phone number</li>
                                <li data-check="address"><i class="bx bxs-check-circle"></i> Address</li>
                                <li data-check="delivery_policy"><i class="bx bxs-check-circle"></i> Delivery policy</li>
                                <li data-check="return_policy"><i class="bx bxs-check-circle"></i> Return &amp; refund policy</li>
                            </ul>
                        </div>
                    </div>

                    <div class="bp-card">
                        <div class="bp-card-h">
                            <span class="ic ic-amber"><i class="bx bx-bot"></i></span>
                            <div>
                                <h5>What AI Copilot can answer</h5>
                                <p>Each filled section becomes a Knowledge Base entry.</p>
                            </div>
                        </div>
                        <div class="bp-card-b py-2">
                            <ul class="bp-qs" id="question-list">
                                <li data-check="business_name"><i class="bx bx-message-rounded-dots"></i> What is the name of your business?</li>
                                <li data-check="hours"><i class="bx bx-message-rounded-dots"></i> What are your business hours?</li>
                                <li data-check="phone"><i class="bx bx-message-rounded-dots"></i> What is your phone number?</li>
                                <li data-check="address"><i class="bx bx-message-rounded-dots"></i> Where are you located?</li>
                                <li data-check="delivery_policy"><i class="bx bx-message-rounded-dots"></i> What is your delivery policy?</li>
                                <li data-check="return_policy"><i class="bx bx-message-rounded-dots"></i> What is your return/refund policy?</li>
                            </ul>
                        </div>
                    </div>

                    <div class="bp-note">
                        <i class="bx bx-info-circle"></i>
                        <div>Entries created from this page are kept in sync each time you save - edit them here rather than in the <a href="{{ route('admin.knowledge-base.index') }}" class="bp-link">Knowledge Base</a>.</div>
                    </div>

                </div>
            </div>
        </div>

        <div class="bp-savebar mt-4" id="savebar">
            <div class="bp-status"><span class="dot"></span><span id="save-status">All changes saved</span></div>
            <div class="actions">
                <button type="button" class="bp-btn bp-btn-outline" id="discard-btn" disabled>Discard</button>
                <button type="submit" class="bp-btn bp-btn-brand" id="save-btn"><i class="bx bx-save"></i> Save Business Profile</button>
            </div>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Deferred until DOMContentLoaded: this page renders inside #app, which the
// deferred Vite module re-mounts as a Vue root - listeners attached any
// earlier would be bound to DOM nodes Vue then throws away.
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('business-profile-form');
    const savebar = document.getElementById('savebar');
    const saveBtn = document.getElementById('save-btn');
    const discardBtn = document.getElementById('discard-btn');
    const dayRows = [...form.querySelectorAll('.bp-day')];

    // "13:30" -> "1:30 PM" - the saved text is copied verbatim into the
    // "What are your business hours?" Knowledge Base answer, so it's
    // written the way a customer reads it.
    function to12h(value) {
        const [h, m] = value.split(':').map(Number);
        return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
    }

    function dayValue(row) {
        const legacy = row.querySelector('.legacy');
        if (legacy) return legacy.value.trim();
        const from = row.querySelector('.t-from').value;
        const to = row.querySelector('.t-to').value;
        return from && to ? `${to12h(from)} - ${to12h(to)}` : '';
    }

    function payload() {
        const data = { hours: {}, closed: [] };
        ['business_name', 'phone', 'address', 'delivery_policy', 'return_policy'].forEach((name) => {
            data[name] = form.elements[name].value.trim();
        });
        dayRows.forEach((row) => {
            const day = row.dataset.day;
            if (!row.querySelector('.open-toggle').checked) data.closed.push(day);
            else data.hours[day] = dayValue(row);
        });
        return data;
    }

    function setOpen(row, open) {
        row.querySelector('.open-toggle').checked = open;
        row.classList.toggle('is-closed', !open);
        row.querySelector('.txt').textContent = open ? 'Open' : 'Closed';
        row.querySelector('.times-open').hidden = !open;
        row.querySelector('.times-closed').hidden = open;
    }

    // --- completeness + "what Copilot can answer" ---
    function refreshStrength() {
        const data = payload();
        const filled = {
            business_name: !!data.business_name,
            hours: data.closed.length > 0 || Object.values(data.hours).some(Boolean),
            phone: !!data.phone,
            address: !!data.address,
            delivery_policy: !!data.delivery_policy,
            return_policy: !!data.return_policy,
        };
        const done = Object.values(filled).filter(Boolean).length;
        const pct = Math.round((done / 6) * 100);
        document.getElementById('strength-ring').style.setProperty('--p', pct);
        document.getElementById('strength-pct').textContent = pct + '%';
        document.getElementById('strength-text').textContent = pct === 100
            ? 'Great - your AI Copilot has everything it needs.'
            : `${6 - done} ${6 - done === 1 ? 'section' : 'sections'} left to help the AI Copilot answer more questions.`;
        document.querySelectorAll('#strength-list li, #question-list li').forEach((li) => {
            const on = filled[li.dataset.check];
            li.classList.toggle(li.closest('#question-list') ? 'on' : 'done', on);
        });
    }

    // --- dirty tracking ---
    let saved = JSON.stringify(payload());
    let initialState = captureState();

    function captureState() {
        return [...form.querySelectorAll('input, textarea')].map((el) => (el.type === 'checkbox' ? el.checked : el.value));
    }

    function refreshDirty() {
        const dirty = JSON.stringify(payload()) !== saved;
        savebar.classList.toggle('dirty', dirty);
        document.getElementById('save-status').textContent = dirty ? 'You have unsaved changes' : 'All changes saved';
        discardBtn.disabled = !dirty;
        return dirty;
    }

    function refreshCounters() {
        form.querySelectorAll('[data-counter]').forEach((el) => {
            document.getElementById(el.dataset.counter).textContent = `${el.value.length} / 5000`;
        });
    }

    function refreshAll() { refreshStrength(); refreshDirty(); refreshCounters(); }

    form.addEventListener('input', refreshAll);
    form.addEventListener('change', (e) => {
        if (e.target.classList.contains('open-toggle')) setOpen(e.target.closest('.bp-day'), e.target.checked);
        refreshAll();
    });

    document.getElementById('copy-monday').addEventListener('click', () => {
        const [mon, ...rest] = dayRows;
        const open = mon.querySelector('.open-toggle').checked;
        const from = mon.querySelector('.t-from')?.value;
        const to = mon.querySelector('.t-to')?.value;
        rest.forEach((row) => {
            setOpen(row, open);
            if (open && row.querySelector('.t-from')) {
                row.querySelector('.t-from').value = from || '';
                row.querySelector('.t-to').value = to || '';
            }
        });
        refreshAll();
    });

    discardBtn.addEventListener('click', () => {
        const els = [...form.querySelectorAll('input, textarea')];
        els.forEach((el, i) => { if (el.type === 'checkbox') el.checked = initialState[i]; else el.value = initialState[i]; });
        dayRows.forEach((row) => setOpen(row, row.querySelector('.open-toggle').checked));
        refreshAll();
    });

    window.addEventListener('beforeunload', (e) => {
        if (refreshDirty()) { e.preventDefault(); e.returnValue = ''; }
    });

    // --- save ---
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const incomplete = dayRows.find((row) => {
            const f = row.querySelector('.t-from');
            return f && row.querySelector('.open-toggle').checked && (!!f.value !== !!row.querySelector('.t-to').value);
        });
        if (incomplete) {
            window.Swal?.fire({ icon: 'warning', title: 'Incomplete hours', text: `Set both an opening and closing time for ${incomplete.querySelector('.bp-day-name').textContent}, or leave both empty.` });
            return;
        }

        const data = payload();
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        fetch('{{ route('admin.ai-copilot.business-profile.update') }}', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('[name=_token]').value,
            },
            body: JSON.stringify(data),
        })
            .then(async (response) => {
                const res = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(res.message || Object.values(res.errors || {}).flat().join(' ') || 'Could not save your Business Profile.');
                }
                saved = JSON.stringify(data);
                initialState = captureState();
                refreshDirty();
                window.Swal?.fire({ icon: 'success', title: res.message || 'Saved.', text: 'Your Knowledge Base has been updated.', timer: 2000, showConfirmButton: false });
            })
            .catch((err) => {
                window.Swal?.fire({ icon: 'error', title: 'Error', text: err.message });
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bx bx-save"></i> Save Business Profile';
            });
    });

    refreshAll();
});
</script>
@endpush
