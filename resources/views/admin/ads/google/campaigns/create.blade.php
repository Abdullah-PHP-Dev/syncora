@extends('layouts.app')

@section('title', 'Create Google Campaign')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/ad-builder.css') }}">
    <style>
        /* Google-specific search-ad preview */
        .campaign-builder .search-preview { padding: 15px; }
        .campaign-builder .search-ad-label { color: #006621; font-size: 12px; font-weight: 600; }
        .campaign-builder .search-ad-headline { color: #1a0dab; font-size: 18px; margin: 4px 0; }
        .campaign-builder .search-ad-url { color: #006621; font-size: 13px; }
        .campaign-builder .search-ad-desc { color: #4d5156; font-size: 13px; margin-top: 4px; }
    </style>
@endpush

@section('content')
    <div class="col-xxl-12 mb-0">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <div class="card px-sm-6 px-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-3">
                            <a href="{{ route('admin.ads.campaigns.index', ['platform' => 'google']) }}">
                                <button class="btn btn-primary btn-sm">
                                    <i class="bx bx-list-ul"></i> {{ __('admin.marketing_tools.ads.campaign.header') }}
                                </button>
                            </a>
                        </div>

                        <div class="campaign-builder brand-google">
                            <div class="builder-header">
                                <div class="social-icon-mini google">
                                    <i class="bx bxl-google"></i>
                                </div>
                                <h2>Create Google Search Campaign</h2>
                                <div class="campaign-steps">
                                    <div class="step active" data-step="1">Campaign</div>
                                    <div class="step" data-step="2">Budget & Bid</div>
                                    <div class="step" data-step="3">Keywords</div>
                                    <div class="step" data-step="4">Ad Copy</div>
                                    <div class="step" data-step="5">Audience</div>
                                    <div class="step" data-step="6">Review</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-8">
                                    <form id="campaign" enctype="multipart/form-data">
                                        @csrf

                                        <div class="wizard-step active" data-step="1">
                                            <div class="builder-card">
                                                <h5>Campaign Information</h5>
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <label>Campaign Name *</label>
                                                        <input type="text" name="name" id="name" class="form-control" required maxlength="255">
                                                        <p class="error-message error-name"></p>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label>Advertising Channel Type *</label>
                                                        <select name="advertising_channel_type" id="advertising_channel_type" class="form-control" required>
                                                            <option value="SEARCH">Search</option>
                                                        </select>
                                                        <small class="form-text text-muted" id="channelTypeHint">Responsive Search Ad campaign - keyword-targeted, Search Network.</small>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <label>Start Date *</label>
                                                        <input type="date" name="start_time" id="start_time" class="form-control" required>
                                                        <p class="error-message error-start_time"></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>End Date *</label>
                                                        <input type="date" name="end_time" id="end_time" class="form-control" required>
                                                        <p class="error-message error-end_time"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="2">
                                            <div class="builder-card">
                                                <h5>Budget & Bidding</h5>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label>Budget Mode *</label>
                                                        <select name="budget_mode" id="budget_mode" class="form-control" required>
                                                            <option value="daily">Daily Budget</option>
                                                            <option value="total">Total (Campaign) Budget</option>
                                                        </select>
                                                        <p class="error-message error-budget_mode"></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>Budget *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">{{ $account->currency ?? 'USD' }}</span>
                                                            <input class="form-control" name="budget" id="budget" type="number" step="0.01" min="1" required>
                                                        </div>
                                                        <p class="error-message error-budget"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <label>Bid Strategy *</label>
                                                        <select name="bid_strategy" id="bid_strategy" class="form-control" required>
                                                            <option value="MAXIMIZE_CONVERSIONS">Maximize Conversions</option>
                                                            <option value="TARGET_CPA">Target CPA</option>
                                                            <option value="TARGET_ROAS">Target ROAS</option>
                                                            <option value="MANUAL_CPC">Manual CPC</option>
                                                            <option value="TARGET_SPEND">Maximize Clicks</option>
                                                        </select>
                                                        <p class="error-message error-bid_strategy"></p>
                                                    </div>
                                                    <div class="col-md-6 bid_amount_group" style="display:none">
                                                        <label>Bid Amount / Target</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">{{ $account->currency ?? 'USD' }}</span>
                                                            <input class="form-control" name="bid_amount" id="bid_amount" type="number" step="0.01" min="0.01">
                                                        </div>
                                                        <p class="error-message error-bid_amount"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="3">
                                            <div class="builder-card">
                                                <h5>Keywords</h5>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label>Match Type *</label>
                                                        <select name="match_type" id="match_type" class="form-control" required>
                                                            <option value="BROAD">Broad Match</option>
                                                            <option value="PHRASE">Phrase Match</option>
                                                            <option value="EXACT">Exact Match</option>
                                                        </select>
                                                        <p class="error-message error-match_type"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Keywords * (one per line)</label>
                                                        <textarea name="keywords" id="keywords" rows="6" class="form-control" required placeholder="running shoes&#10;buy sneakers online&#10;best trainers"></textarea>
                                                        <p class="error-message error-keywords"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="4">
                                            <div class="builder-card">
                                                <h5>Ad Copy (Responsive Search Ad)</h5>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <label>Final URL *</label>
                                                        <input type="url" name="target_link" id="target_link" class="form-control" required placeholder="https://example.com">
                                                        <p class="error-message error-target_link"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Headlines * (one per line, 3-15 headlines, max 30 characters each)</label>
                                                        <textarea name="headlines" id="headlines" rows="6" class="form-control" required placeholder="Best Running Shoes Online&#10;Free Shipping Today&#10;Shop the New Collection"></textarea>
                                                        <small class="form-text text-muted" id="headlinesCount"></small>
                                                        <p class="error-message error-headlines"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Descriptions * (one per line, 2-4 descriptions, max 90 characters each)</label>
                                                        <textarea name="descriptions" id="descriptions" rows="4" class="form-control" required placeholder="Get the best deals on running shoes. Shop now and save.&#10;Free returns within 30 days. Order today."></textarea>
                                                        <small class="form-text text-muted" id="descriptionsCount"></small>
                                                        <p class="error-message error-descriptions"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="5">
                                            <div class="builder-card">
                                                <h5>Audience Targeting</h5>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <label>Gender</label>
                                                        <select name="gender" id="gender" class="form-control">
                                                            <option value="both">All</option>
                                                            <option value="male">Male</option>
                                                            <option value="female">Female</option>
                                                        </select>
                                                        <p class="error-message error-gender"></p>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label>Age Range</label>
                                                        <div class="checkbox-group">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_18_24" id="age_18_24">
                                                                <label class="form-check-label" for="age_18_24">18 – 24</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_25_34" id="age_25_34">
                                                                <label class="form-check-label" for="age_25_34">25 – 34</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_35_44" id="age_35_44">
                                                                <label class="form-check-label" for="age_35_44">35 – 44</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_45_54" id="age_45_54">
                                                                <label class="form-check-label" for="age_45_54">45 – 54</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_55_64" id="age_55_64">
                                                                <label class="form-check-label" for="age_55_64">55 – 64</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_65_UP" id="age_65_up">
                                                                <label class="form-check-label" for="age_65_up">65+</label>
                                                            </div>
                                                        </div>
                                                        <p class="error-message error-age_range"></p>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label>Countries *</label>
                                                        <select name="countries[]" id="countries" multiple class="form-control" required>
                                                            @foreach ($countries as $country)
                                                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p class="error-message error-countries"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Languages</label>
                                                        <div class="platform-group">
                                                            @foreach (['en' => 'English', 'ar' => 'Arabic', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'ja' => 'Japanese', 'ko' => 'Korean', 'pt' => 'Portuguese', 'ru' => 'Russian', 'zh' => 'Chinese'] as $code => $langName)
                                                                <div class="platform-card">
                                                                    <div class="form-check form-switch">
                                                                        <input class="form-check-input platform-switch" type="checkbox" name="languages[]" value="{{ $code }}" id="lang_{{ $code }}" {{ $code == 'en' ? 'checked' : '' }}>
                                                                        <label class="form-check-label ms-2" for="lang_{{ $code }}">{{ $langName }}</label>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        <p class="error-message error-languages"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="6">
                                            <div class="builder-card">
                                                <h5>Review</h5>
                                                <p class="text-muted">Please review your campaign details before launching. Google reviews new campaigns before they start serving, so this launches as PAUSED - activate it from the campaign list once ready.</p>
                                                <div id="reviewSummary"></div>
                                            </div>
                                        </div>

                                        <div class="wizard-nav">
                                            <button type="button" class="btn btn-outline-primary" id="prevStep" style="display:none">Previous</button>
                                            <button type="button" class="btn btn-primary ms-auto" id="nextStep">Next</button>
                                            <button type="submit" class="btn btn-primary ms-auto" id="launchBtn" style="display:none">Launch</button>
                                        </div>
                                    </form>
                                </div>

                                <div class="col-lg-4">
                                    <div class="preview-card">
                                        <div class="preview-header">Search Ad Preview</div>
                                        <div class="search-preview">
                                            <div class="search-ad-label">Ad · <span id="previewUrl">example.com</span></div>
                                            <div class="search-ad-headline" id="previewHeadline">Your Headline Here</div>
                                            <div class="search-ad-desc" id="previewDescription">Your ad description will appear here as you type.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // The shared layout mounts a Vue 2 root on #app with no template/
        // render option (resources/js/app.js), so Vue re-compiles this
        // server-rendered form as an in-DOM template shortly after load and
        // swaps the nodes out from under any listeners attached earlier -
        // deferring to `load` runs this against the DOM Vue actually leaves
        // in place. See the Facebook/TikTok/Snapchat campaign builders for
        // the full explanation.
        window.addEventListener('load', function() {

        if ($.fn.select2) {
            $('#countries').select2();
        }

        const channelTypeSelect = document.getElementById('advertising_channel_type');
        const bidStrategySelect = document.getElementById('bid_strategy');
        const bidAmountGroup = document.querySelector('.bid_amount_group');

        // Mirrors GoogleAdService::biddingStrategyPayload()'s match arms
        // exactly - only SEARCH is buildable by this form today, but
        // keeping the bid-strategy list driven by channel type (rather
        // than hardcoded in the <select>) means a future channel type
        // can't silently end up offering a bidding strategy the backend
        // has no case for.
        const CHANNEL_TYPE_BID_STRATEGIES = {
            SEARCH: [
                ['MAXIMIZE_CONVERSIONS', 'Maximize Conversions'],
                ['TARGET_CPA', 'Target CPA'],
                ['TARGET_ROAS', 'Target ROAS'],
                ['MANUAL_CPC', 'Manual CPC'],
                ['TARGET_SPEND', 'Maximize Clicks'],
            ],
        };

        const CHANNEL_TYPE_HINTS = {
            SEARCH: 'Responsive Search Ad campaign - keyword-targeted, Search Network.',
        };

        function syncBidStrategyOptions() {
            const strategies = CHANNEL_TYPE_BID_STRATEGIES[channelTypeSelect.value] || [];
            const previous = bidStrategySelect.value;

            bidStrategySelect.innerHTML = strategies
                .map(([value, label]) => `<option value="${value}">${label}</option>`)
                .join('');

            // Keep the previous pick if it's still valid for the new
            // channel type, otherwise fall back to the first option.
            if (strategies.some(([value]) => value === previous)) {
                bidStrategySelect.value = previous;
            }

            document.getElementById('channelTypeHint').textContent =
                CHANNEL_TYPE_HINTS[channelTypeSelect.value] || '';

            toggleBidAmount();
        }

        function toggleBidAmount() {
            // MANUAL_CPC needs this too - storeAdGroup() reads bid_amount as
            // the ad group's max CPC bid (cpcBidMicros) for that strategy.
            const needsAmount = ['TARGET_CPA', 'TARGET_ROAS', 'MANUAL_CPC'].includes(bidStrategySelect.value);
            bidAmountGroup.style.display = needsAmount ? '' : 'none';
        }

        channelTypeSelect.addEventListener('change', syncBidStrategyOptions);
        bidStrategySelect.addEventListener('change', toggleBidAmount);
        syncBidStrategyOptions();

        function beautifyLabel(value) {
            return (value || '').toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        // ------------------------------------------------------------------
        // LIVE SEARCH AD PREVIEW
        // ------------------------------------------------------------------
        function nonEmptyLines(value) {
            return value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean);
        }

        // Lines past `max` are silently dropped server-side
        // (GoogleAdService::storeAd() caps at Google's RSA limits) - this
        // just surfaces that before submit instead of the count quietly
        // shrinking with no explanation.
        function updateLineCountHint(textareaId, hintId, min, max) {
            const lines = nonEmptyLines(document.getElementById(textareaId).value);
            const hint = document.getElementById(hintId);
            const over = lines.length > max;

            hint.textContent = over
                ? `${lines.length} entered - only the first ${max} will be used.`
                : `${lines.length} of ${min}-${max}`;
            hint.classList.toggle('text-danger', over);
            hint.classList.toggle('text-muted', !over);
        }

        document.getElementById('headlines')?.addEventListener('input', function() {
            const first = nonEmptyLines(this.value)[0];
            document.getElementById('previewHeadline').innerText = first || 'Your Headline Here';
            updateLineCountHint('headlines', 'headlinesCount', 3, 15);
        });

        document.getElementById('descriptions')?.addEventListener('input', function() {
            const first = nonEmptyLines(this.value)[0];
            document.getElementById('previewDescription').innerText = first || 'Your ad description will appear here as you type.';
            updateLineCountHint('descriptions', 'descriptionsCount', 2, 4);
        });

        updateLineCountHint('headlines', 'headlinesCount', 3, 15);
        updateLineCountHint('descriptions', 'descriptionsCount', 2, 4);

        document.getElementById('target_link')?.addEventListener('input', function() {
            try {
                const url = new URL(this.value);
                document.getElementById('previewUrl').innerText = url.hostname;
            } catch (e) {
                document.getElementById('previewUrl').innerText = this.value || 'example.com';
            }
        });

        // ------------------------------------------------------------------
        // WIZARD STEP NAVIGATION
        // ------------------------------------------------------------------
        const wizardSteps = document.querySelectorAll('.wizard-step');
        const stepPills = document.querySelectorAll('.campaign-steps .step');
        const totalSteps = wizardSteps.length;
        let currentStep = 1;

        function showStep(step) {
            if (step < 1 || step > totalSteps) return;

            currentStep = step;

            wizardSteps.forEach(section => {
                section.classList.toggle('active', parseInt(section.dataset.step) === step);
            });

            stepPills.forEach(pill => {
                pill.classList.toggle('active', parseInt(pill.dataset.step) === step);
            });

            document.getElementById('prevStep').style.display = step === 1 ? 'none' : 'inline-block';
            document.getElementById('nextStep').style.display = step === totalSteps ? 'none' : 'inline-block';
            document.getElementById('launchBtn').style.display = step === totalSteps ? 'inline-block' : 'none';

            if (step === totalSteps) {
                populateReviewSummary();
            }

            window.scrollTo({
                top: document.querySelector('.campaign-builder').offsetTop - 20,
                behavior: 'smooth'
            });
        }

        function stepIsValid(stepNumber) {
            let stepEl = document.querySelector(`.wizard-step[data-step="${stepNumber}"]`);
            let fields = stepEl.querySelectorAll('input, select, textarea');

            for (let field of fields) {
                if (field.closest('[style*="display: none"]')) continue;
                if (!field.checkValidity()) {
                    showStep(stepNumber);
                    field.reportValidity();
                    return false;
                }
            }

            return true;
        }

        document.getElementById('nextStep').addEventListener('click', function() {
            if (!stepIsValid(currentStep)) return;
            showStep(currentStep + 1);
        });

        document.getElementById('prevStep').addEventListener('click', function() {
            showStep(currentStep - 1);
        });

        stepPills.forEach(pill => {
            pill.addEventListener('click', function() {
                let target = parseInt(this.dataset.step);

                if (target > currentStep) {
                    for (let s = currentStep; s < target; s++) {
                        if (!stepIsValid(s)) return;
                    }
                }

                showStep(target);
            });
        });

        function reviewRow(label, value) {
            return `<div class="review-row"><span>${label}</span><span>${value || '-'}</span></div>`;
        }

        function populateReviewSummary() {
            let countries = $('#countries option:selected').map(function() {
                return this.text;
            }).get().join(', ');

            let languages = Array.from(document.querySelectorAll('input[name="languages[]"]:checked'))
                .map(el => el.nextElementSibling.innerText.trim()).join(', ');

            let ageRanges = Array.from(document.querySelectorAll('input[name="age_range[]"]:checked'))
                .map(el => beautifyLabel(el.value.replace('AGE_RANGE_', ''))).join(', ');

            let keywordCount = document.getElementById('keywords').value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean).length;
            let headlineCount = document.getElementById('headlines').value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean).length;
            let descriptionCount = document.getElementById('descriptions').value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean).length;

            let html = '';
            html += reviewRow('Campaign Name', document.getElementById('name').value);
            html += reviewRow('Start Date', document.getElementById('start_time').value);
            html += reviewRow('End Date', document.getElementById('end_time').value);
            html += reviewRow('Budget Mode', beautifyLabel(document.getElementById('budget_mode').value));
            html += reviewRow('Budget', document.getElementById('budget').value);
            html += reviewRow('Bid Strategy', beautifyLabel(bidStrategySelect.value));
            html += reviewRow('Match Type', beautifyLabel(document.getElementById('match_type').value));
            html += reviewRow('Keywords', keywordCount + ' keyword(s)');
            html += reviewRow('Headlines', headlineCount + ' headline(s)');
            html += reviewRow('Descriptions', descriptionCount + ' description(s)');
            html += reviewRow('Final URL', document.getElementById('target_link').value);
            html += reviewRow('Countries', countries);
            html += reviewRow('Age Range', ageRanges);
            html += reviewRow('Gender', beautifyLabel(document.getElementById('gender').value));
            html += reviewRow('Languages', languages);

            document.getElementById('reviewSummary').innerHTML = html;
        }

        // Laravel validation errors land in .error-<field> elements across
        // all six wizard steps - api.js populates them then fires this
        // event. Jump to whichever step holds the first one.
        $(document).on('campaign:validationErrors', function(e, errors) {
            if (!errors) return;

            stepPills.forEach(pill => pill.classList.remove('has-error'));

            let targetStep = null;
            let targetField = null;

            Object.keys(errors).forEach(field => {
                let fieldEl = document.querySelector('.error-' + field);
                if (!fieldEl) return;

                let stepEl = fieldEl.closest('.wizard-step');
                if (!stepEl) return;

                let stepNumber = parseInt(stepEl.dataset.step);
                let pill = document.querySelector(`.campaign-steps .step[data-step="${stepNumber}"]`);
                if (pill) pill.classList.add('has-error');

                if (targetStep === null || stepNumber < targetStep) {
                    targetStep = stepNumber;
                    targetField = fieldEl;
                }
            });

            if (targetStep !== null) {
                showStep(targetStep);
                targetField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        $('#campaign').on('input change', 'input, select, textarea', function() {
            let name = this.name ? this.name.replace('[]', '') : null;
            if (!name) return;

            let errorEl = document.querySelector('.error-' + name);
            if (!errorEl || errorEl.textContent.trim() === '') return;

            errorEl.textContent = '';

            let stepEl = errorEl.closest('.wizard-step');
            if (!stepEl) return;

            let stillHasErrors = Array.from(stepEl.querySelectorAll('.error-message'))
                .some(el => el.textContent.trim() !== '');

            if (!stillHasErrors) {
                let pill = document.querySelector(`.campaign-steps .step[data-step="${stepEl.dataset.step}"]`);
                if (pill) pill.classList.remove('has-error');
            }
        });

        showStep(1);
        }); // end window.addEventListener('load', ...)

        // ------------------------------------------------------------------
        // GLOBAL VARIABLES FOR API SUBMISSION - kept outside the load-deferred
        // block since api.js (a separate script) reads them as globals.
        // ------------------------------------------------------------------
        var url = "{{ route('admin.ads.campaigns.store', ['platform' => 'google']) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'google']) }}";
        var method = 'POST';
    </script>
    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
    <script src="{{ asset('assets/js/ad-builder.js') }}"></script>
@endpush
