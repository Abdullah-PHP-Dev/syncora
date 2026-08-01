@extends('layouts.app')

@section('title', 'Create X Campaign')

<style>
    .campaign-builder {
        max-width: 1400px;
        margin: auto;
    }

    .builder-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .campaign-steps {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .step {
        background: #f3f5f8;
        padding: 10px 20px;
        border-radius: 30px;
        cursor: pointer;
        transition: .3s;
    }

    .step.active {
        background: #000;
        color: #fff;
        box-shadow: 0 5px 15px rgba(0, 0, 0, .3);
    }

    .builder-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, .08);
    }

    .platform-group {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .platform-card {
        min-width: 200px;
        padding: 15px 20px;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        background: #fff;
        transition: .3s;
    }

    .upload-zone {
        border: 2px dashed #d8dce3;
        border-radius: 15px;
        padding: 50px;
        text-align: center;
    }

    .upload-zone i {
        font-size: 50px;
        color: #000;
    }

    .preview-card {
        position: sticky;
        top: 20px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, .08);
        overflow: hidden;
    }

    .preview-header {
        background: #000;
        color: white;
        padding: 15px;
        font-weight: 600;
    }

    .tweet-preview {
        padding: 15px;
    }

    .tweet-top {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .avatar {
        width: 45px;
        height: 45px;
        background: #ddd;
        border-radius: 50%;
    }

    .tweet-text {
        margin: 10px 0;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .tweet-media {
        width: 100%;
        border-radius: 12px;
        margin-top: 8px;
    }

    .tweet-counter {
        text-align: right;
        font-size: 0.8rem;
        color: #6b7280;
    }

    .tweet-counter.over {
        color: #dc3545;
        font-weight: 600;
    }

    .error-message {
        color: red;
        font-size: 0.8rem;
        margin-top: 5px;
    }

    .wizard-step {
        display: none;
    }

    .wizard-step.active {
        display: block;
    }

    .review-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #eef1f5;
    }

    .review-row:last-child {
        border-bottom: none;
    }

    .review-row span:first-child {
        color: #6b7280;
    }

    .review-row span:last-child {
        font-weight: 600;
        text-align: right;
    }

    .wizard-nav {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
    }

    .step.has-error {
        box-shadow: 0 0 0 2px #dc3545;
    }

    .step.has-error::after {
        content: '!';
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        margin-left: 6px;
        border-radius: 50%;
        background: #dc3545;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }
</style>

@section('content')
    <div class="col-xxl-12 mb-0">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <div class="card px-sm-6 px-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-3">
                            <a href="{{ route('admin.ads.campaigns.index', ['platform' => 'x']) }}">
                                <button class="btn btn-primary btn-sm">
                                    <i class="bx bx-list-ul"></i> {{ __('admin.marketing_tools.ads.campaign.header') }}
                                </button>
                            </a>
                        </div>

                        <div class="campaign-builder">
                            <div class="builder-header">
                                <div class="social-icon-mini x">
                                    <i class="bx bxl-twitter"></i>
                                </div>
                                <h2>Create X Campaign (Promoted Tweets)</h2>
                                <div class="campaign-steps">
                                    <div class="step active" data-step="1">Campaign</div>
                                    <div class="step" data-step="2">Budget & Bid</div>
                                    <div class="step" data-step="3">Tweet</div>
                                    <div class="step" data-step="4">Audience</div>
                                    <div class="step" data-step="5">Review</div>
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
                                                    <div class="col-md-12">
                                                        <label>Campaign Name *</label>
                                                        <input type="text" name="name" id="name" class="form-control" required maxlength="255">
                                                        <p class="error-message error-name"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Funding Instrument ID *</label>
                                                        <input type="text" name="funding_instrument_id" id="funding_instrument_id" class="form-control" required placeholder="e.g. lfa2z">
                                                        <small class="text-muted">Funding sources can't be created via the API - find this under Billing on ads.x.com and paste its ID here.</small>
                                                        <p class="error-message error-funding_instrument_id"></p>
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
                                                        <label>Objective *</label>
                                                        <select name="objective" id="objective" class="form-control" required>
                                                            <option value="REACH">Reach</option>
                                                            <option value="ENGAGEMENTS">Engagements</option>
                                                            <option value="FOLLOWERS">Followers</option>
                                                            <option value="WEBSITE_CLICKS">Website Clicks</option>
                                                            <option value="APP_INSTALLS">App Installs</option>
                                                            <option value="APP_ENGAGEMENTS">App Engagements</option>
                                                            <option value="VIDEO_VIEWS">Video Views</option>
                                                            <option value="PREROLL_VIEWS">Pre-roll Views</option>
                                                        </select>
                                                        <p class="error-message error-objective"></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>Bid Type *</label>
                                                        <select name="bid_type" id="bid_type" class="form-control" required>
                                                            <option value="AUTO">Autobid</option>
                                                            <option value="MAX">Max Bid</option>
                                                            <option value="TARGET">Target Bid</option>
                                                        </select>
                                                        <p class="error-message error-bid_type"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3 bid_amount_group" style="display:none">
                                                    <div class="col-md-6">
                                                        <label>Bid Amount</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">{{ $account->currency ?? 'USD' }}</span>
                                                            <input class="form-control" name="bid_amount" id="bid_amount" type="number" step="0.01" min="0.01">
                                                        </div>
                                                        <p class="error-message error-bid_amount"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Placements *</label>
                                                        <div class="platform-group">
                                                            @foreach (['ALL_ON_TWITTER' => 'All on X', 'TWITTER_TIMELINE' => 'Timeline', 'TWITTER_PROFILE' => 'Profile', 'TWITTER_SEARCH' => 'Search', 'PUBLISHER_NETWORK' => 'Publisher Network', 'SPOTLIGHT' => 'Spotlight', 'TREND' => 'Trend'] as $value => $label)
                                                                <div class="platform-card">
                                                                    <div class="form-check form-switch">
                                                                        <input class="form-check-input platform-switch placement-check" type="checkbox" name="placements[]" value="{{ $value }}" id="placement_{{ $value }}" {{ $value === 'ALL_ON_TWITTER' ? 'checked' : '' }}>
                                                                        <label class="form-check-label ms-2" for="placement_{{ $value }}">{{ $label }}</label>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        <p class="error-message error-placements"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="3">
                                            <div class="builder-card">
                                                <h5>Tweet Creative</h5>
                                                <p class="text-muted">This creates a promoted-only ("nullcast") Tweet - it runs as an ad but is not published to your account's normal timeline.</p>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <label>Tweet Text *</label>
                                                        <textarea name="message" id="message" rows="4" class="form-control" required maxlength="280" placeholder="What's happening?"></textarea>
                                                        <p class="tweet-counter" id="tweetCounter">0 / 280</p>
                                                        <p class="error-message error-message"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Landing Page URL *</label>
                                                        <input type="url" name="target_link" id="target_link" class="form-control" required placeholder="https://example.com">
                                                        <small class="text-muted">Appended to the Tweet text - X automatically unfurls it into a link preview card.</small>
                                                        <p class="error-message error-target_link"></p>
                                                    </div>
                                                </div>
                                                <br>
                                                <div class="upload-zone">
                                                    <i class="bx bx-cloud-upload"></i>
                                                    <h6>Media (optional)</h6>
                                                    <p>Image (max 15MB) or video (max 500MB).</p>
                                                    <input type="file" name="media[]" id="mediaInput" hidden accept="image/*,video/*">
                                                    <button type="button" class="btn btn-primary" onclick="document.getElementById('mediaInput').click()">Upload Media</button>
                                                    <p class="error-message error-media"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="4">
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
                                                    <div class="col-md-8">
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

                                        <div class="wizard-step" data-step="5">
                                            <div class="builder-card">
                                                <h5>Review</h5>
                                                <p class="text-muted">Please review your campaign details before launching. This launches PAUSED - activate it from the campaign list once ready.</p>
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
                                        <div class="preview-header">Tweet Preview</div>
                                        <div class="tweet-preview">
                                            <div class="tweet-top">
                                                <div class="avatar"></div>
                                                <div>
                                                    <strong>{{ $account->name ?? 'Your Account' }}</strong>
                                                    <div class="text-muted" style="font-size:0.85rem">Promoted</div>
                                                </div>
                                            </div>
                                            <div class="tweet-text" id="previewText">Your Tweet text will appear here...</div>
                                            <img id="previewImage" class="tweet-media" style="display:none">
                                            <video id="previewVideo" class="tweet-media" controls style="display:none"></video>
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
        // See the Facebook/TikTok/Snapchat campaign builders for why this
        // whole block waits for `load`: the shared layout's Vue 2 root on
        // #app re-compiles this server-rendered form as an in-DOM template
        // shortly after page load and swaps the nodes out from under any
        // listeners attached earlier.
        window.addEventListener('load', function() {

        if ($.fn.select2) {
            $('#countries').select2();
        }

        function beautifyLabel(value) {
            return (value || '').toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        const bidTypeSelect = document.getElementById('bid_type');
        const bidAmountGroup = document.querySelector('.bid_amount_group');

        function toggleBidAmount() {
            bidAmountGroup.style.display = bidTypeSelect.value === 'AUTO' ? 'none' : '';
        }

        bidTypeSelect.addEventListener('change', toggleBidAmount);
        toggleBidAmount();

        // "All on X" is mutually exclusive with the more specific
        // placements in X's own placement model.
        document.querySelectorAll('.placement-check').forEach(function(box) {
            box.addEventListener('change', function() {
                if (this.value === 'ALL_ON_TWITTER' && this.checked) {
                    document.querySelectorAll('.placement-check').forEach(function(other) {
                        if (other !== box) other.checked = false;
                    });
                } else if (this.checked) {
                    document.getElementById('placement_ALL_ON_TWITTER').checked = false;
                }
            });
        });

        // ------------------------------------------------------------------
        // LIVE TWEET PREVIEW
        // ------------------------------------------------------------------
        const messageInput = document.getElementById('message');
        const counter = document.getElementById('tweetCounter');

        function updateTweetPreview() {
            const text = messageInput.value;
            document.getElementById('previewText').innerText = text || 'Your Tweet text will appear here...';
            counter.innerText = text.length + ' / 280';
            counter.classList.toggle('over', text.length > 280);
        }

        messageInput?.addEventListener('input', updateTweetPreview);
        updateTweetPreview();

        document.getElementById('mediaInput')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const image = document.getElementById('previewImage');
            const video = document.getElementById('previewVideo');
            if (!file) return;

            image.style.display = 'none';
            video.style.display = 'none';

            const url = URL.createObjectURL(file);
            if (file.type.startsWith('video/')) {
                video.src = url;
                video.style.display = 'block';
            } else {
                image.src = url;
                image.style.display = 'block';
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

            let placements = Array.from(document.querySelectorAll('.placement-check:checked'))
                .map(el => el.nextElementSibling.innerText.trim()).join(', ');

            let html = '';
            html += reviewRow('Campaign Name', document.getElementById('name').value);
            html += reviewRow('Start Date', document.getElementById('start_time').value);
            html += reviewRow('End Date', document.getElementById('end_time').value);
            html += reviewRow('Budget Mode', beautifyLabel(document.getElementById('budget_mode').value));
            html += reviewRow('Budget', document.getElementById('budget').value);
            html += reviewRow('Objective', beautifyLabel(document.getElementById('objective').value));
            html += reviewRow('Bid Type', beautifyLabel(bidTypeSelect.value));
            html += reviewRow('Placements', placements);
            html += reviewRow('Tweet', document.getElementById('message').value);
            html += reviewRow('Landing Page', document.getElementById('target_link').value);
            html += reviewRow('Countries', countries);
            html += reviewRow('Gender', beautifyLabel(document.getElementById('gender').value));
            html += reviewRow('Languages', languages);

            document.getElementById('reviewSummary').innerHTML = html;
        }

        // Laravel validation errors land in .error-<field> elements across
        // all five wizard steps - api.js populates them then fires this
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
        var url = "{{ route('admin.ads.campaigns.store', ['platform' => 'x']) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'x']) }}";
        var method = 'POST';
    </script>
    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
