@extends('layouts.app')

@section('title', 'Edit X Campaign')

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
    @php
        $adGroup = $campaign->adGroups->first();
        $creative = $adGroup->creatives->first();
        $selectedCountryIds = json_decode($adGroup->location_ids ?? '[]') ?: [];
        $selectedLanguages = json_decode($adGroup->languages ?? '[]') ?: [];
        $selectedPlacements = json_decode($adGroup->placements ?? '[]') ?: [];
    @endphp

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
                                <h2>Edit X Campaign</h2>
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
                                        @method('PUT')

                                        <div class="wizard-step active" data-step="1">
                                            <div class="builder-card">
                                                <h5>Campaign Information</h5>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <label>Campaign Name *</label>
                                                        <input type="text" name="name" id="name" class="form-control" required maxlength="255" value="{{ $campaign->name }}">
                                                        <p class="error-message error-name"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <label>Start Date *</label>
                                                        <input type="date" name="start_time" id="start_time" class="form-control" required value="{{ \Carbon\Carbon::parse($campaign->start_time)->format('Y-m-d') }}">
                                                        <p class="error-message error-start_time"></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>End Date *</label>
                                                        <input type="date" name="end_time" id="end_time" class="form-control" required value="{{ \Carbon\Carbon::parse($campaign->end_time)->format('Y-m-d') }}">
                                                        <p class="error-message error-end_time"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="2">
                                            <div class="builder-card">
                                                <h5>Budget & Bidding</h5>
                                                <p class="text-muted">Budget, objective and bid were locked in at creation on X's side - shown here for reference only.</p>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label>Budget Mode</label>
                                                        <input type="text" class="form-control" value="{{ Str::title(str_replace('_', ' ', $campaign->budget_mode)) }}" disabled>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>Budget</label>
                                                        <input type="text" class="form-control" value="{{ $account->metadata['currency'] ?? 'USD' }} {{ $campaign->budget }}" disabled>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <label>Objective</label>
                                                        <input type="text" class="form-control" value="{{ Str::title(str_replace('_', ' ', $adGroup->objective)) }}" disabled>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>Bid Type</label>
                                                        <input type="text" class="form-control" value="{{ Str::title($adGroup->bid_type ?? '') }}" disabled>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Placements</label>
                                                        <input type="text" class="form-control" value="{{ implode(', ', $selectedPlacements) }}" disabled>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="3">
                                            <div class="builder-card">
                                                <h5>Tweet Creative</h5>
                                                <p class="text-muted">Tweets are immutable on X once published - the text and media below can't be changed here. Create a new campaign to change them.</p>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <label>Tweet Text</label>
                                                        <textarea class="form-control" rows="4" disabled>{{ $creative->message }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Landing Page URL</label>
                                                        <input type="text" class="form-control" value="{{ $creative->url }}" disabled>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="4">
                                            <div class="builder-card">
                                                <h5>Audience Targeting</h5>
                                                <p class="text-muted">Targeting was locked in at creation - shown here for reference only.</p>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <label>Gender</label>
                                                        <input type="text" class="form-control" value="{{ Str::title($adGroup->gender ?? 'Both') }}" disabled>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label>Countries</label>
                                                        <input type="text" class="form-control" value="{{ \App\Models\Country::whereIn('id', $selectedCountryIds)->pluck('name')->implode(', ') }}" disabled>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Languages</label>
                                                        <input type="text" class="form-control" value="{{ implode(', ', $selectedLanguages) }}" disabled>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="5">
                                            <div class="builder-card">
                                                <h5>Review</h5>
                                                <p class="text-muted">Please review your changes before saving.</p>
                                                <div id="reviewSummary"></div>
                                            </div>
                                        </div>

                                        <div class="wizard-nav">
                                            <button type="button" class="btn btn-outline-primary" id="prevStep" style="display:none">Previous</button>
                                            <button type="button" class="btn btn-primary ms-auto" id="nextStep">Next</button>
                                            <button type="submit" class="btn btn-primary ms-auto" id="launchBtn" style="display:none">Save Changes</button>
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
                                            <div class="tweet-text">{{ $creative->message }}</div>
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
            let fields = stepEl.querySelectorAll('input:not([disabled]), select:not([disabled]), textarea:not([disabled])');

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
            let html = '';
            html += reviewRow('Campaign Name', document.getElementById('name').value);
            html += reviewRow('Start Date', document.getElementById('start_time').value);
            html += reviewRow('End Date', document.getElementById('end_time').value);

            document.getElementById('reviewSummary').innerHTML = html;
        }

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

        var url = "{{ route('admin.ads.campaigns.update', ['platform' => 'x', 'campaign' => $campaign->id]) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'x']) }}";
        var method = 'PUT';
    </script>
    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
