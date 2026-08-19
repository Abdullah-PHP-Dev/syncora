@extends('layouts.app')

@section('title', 'Edit Google Campaign')

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
        background: #4285F4;
        color: #fff;
        box-shadow: 0 5px 15px rgba(66, 133, 244, .3);
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

    .preview-card {
        position: sticky;
        top: 20px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, .08);
        overflow: hidden;
    }

    .preview-header {
        background: #4285F4;
        color: white;
        padding: 15px;
        font-weight: 600;
    }

    .search-preview {
        padding: 15px;
    }

    .search-ad-label {
        color: #006621;
        font-size: 12px;
        font-weight: 600;
    }

    .search-ad-headline {
        color: #1a0dab;
        font-size: 18px;
        margin: 4px 0;
    }

    .search-ad-url {
        color: #006621;
        font-size: 13px;
    }

    .search-ad-desc {
        color: #4d5156;
        font-size: 13px;
        margin-top: 4px;
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
        $selectedAgeRanges = json_decode($adGroup->age_groups ?? '[]') ?: [];
        $keywordLines = collect(json_decode($adGroup->keywords ?? '[]') ?: [])->pluck('text')->implode("\n");
        $headlineLines = implode("\n", json_decode($creative->headlines ?? '[]') ?: []);
        $descriptionLines = implode("\n", json_decode($creative->descriptions ?? '[]') ?: []);
    @endphp

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

                        <div class="campaign-builder">
                            <div class="builder-header">
                                <div class="social-icon-mini google">
                                    <i class="bx bxl-google"></i>
                                </div>
                                <h2>Edit Google Search Campaign</h2>
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
                                                <p class="text-muted">Budget and bid strategy were locked in at creation on Google's side - shown here for reference only.</p>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <label>Budget Mode</label>
                                                        <input type="text" class="form-control" value="{{ Str::title(str_replace('_', ' ', $campaign->budget_mode)) }}" disabled>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>Budget</label>
                                                        <input type="text" class="form-control" value="{{ $account->currency ?? 'USD' }} {{ $campaign->budget }}" disabled>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <label>Bid Strategy</label>
                                                        <input type="text" class="form-control" value="{{ Str::title(str_replace('_', ' ', $campaign->bidding_strategy)) }}" disabled>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label>Bid Amount</label>
                                                        <input type="text" class="form-control" value="{{ $campaign->bidding_amount ?? '-' }}" disabled>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="3">
                                            <div class="builder-card">
                                                <h5>Keywords</h5>
                                                <p class="text-muted">Keywords are locked in at creation - editing here updates your local record only, not the live ad group.</p>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <label>Keywords</label>
                                                        <textarea class="form-control" rows="6" disabled>{{ $keywordLines }}</textarea>
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
                                                        <input type="url" name="target_link" id="target_link" class="form-control" required value="{{ $creative->url }}">
                                                        <p class="error-message error-target_link"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Headlines * (one per line, 3-15 headlines, max 30 characters each)</label>
                                                        <textarea name="headlines" id="headlines" rows="6" class="form-control" required>{{ $headlineLines }}</textarea>
                                                        <p class="error-message error-headlines"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Descriptions * (one per line, 2-4 descriptions, max 90 characters each)</label>
                                                        <textarea name="descriptions" id="descriptions" rows="4" class="form-control" required>{{ $descriptionLines }}</textarea>
                                                        <p class="error-message error-descriptions"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="5">
                                            <div class="builder-card">
                                                <h5>Audience Targeting</h5>
                                                <p class="text-muted">Targeting was locked in at creation - shown here for reference only.</p>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <label>Gender</label>
                                                        <input type="text" class="form-control" value="{{ Str::title($adGroup->gender ?? 'Both') }}" disabled>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label>Age Range</label>
                                                        <input type="text" class="form-control" value="{{ implode(', ', $selectedAgeRanges) ?: 'All ages' }}" disabled>
                                                    </div>
                                                    <div class="col-md-4">
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

                                        <div class="wizard-step" data-step="6">
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
                                        <div class="preview-header">Search Ad Preview</div>
                                        <div class="search-preview">
                                            <div class="search-ad-label">Ad · <span id="previewUrl">{{ parse_url($creative->url ?? '', PHP_URL_HOST) ?: 'example.com' }}</span></div>
                                            <div class="search-ad-headline" id="previewHeadline">{{ json_decode($creative->headlines ?? '[]')[0] ?? 'Your Headline Here' }}</div>
                                            <div class="search-ad-desc" id="previewDescription">{{ json_decode($creative->descriptions ?? '[]')[0] ?? 'Your ad description will appear here as you type.' }}</div>
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

        function beautifyLabel(value) {
            return (value || '').toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        document.getElementById('headlines')?.addEventListener('input', function() {
            const first = this.value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean)[0];
            document.getElementById('previewHeadline').innerText = first || 'Your Headline Here';
        });

        document.getElementById('descriptions')?.addEventListener('input', function() {
            const first = this.value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean)[0];
            document.getElementById('previewDescription').innerText = first || 'Your ad description will appear here as you type.';
        });

        document.getElementById('target_link')?.addEventListener('input', function() {
            try {
                const url = new URL(this.value);
                document.getElementById('previewUrl').innerText = url.hostname;
            } catch (e) {
                document.getElementById('previewUrl').innerText = this.value || 'example.com';
            }
        });

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
            let headlineCount = document.getElementById('headlines').value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean).length;
            let descriptionCount = document.getElementById('descriptions').value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean).length;

            let html = '';
            html += reviewRow('Campaign Name', document.getElementById('name').value);
            html += reviewRow('Start Date', document.getElementById('start_time').value);
            html += reviewRow('End Date', document.getElementById('end_time').value);
            html += reviewRow('Headlines', headlineCount + ' headline(s)');
            html += reviewRow('Descriptions', descriptionCount + ' description(s)');
            html += reviewRow('Final URL', document.getElementById('target_link').value);

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

        var url = "{{ route('admin.ads.campaigns.update', ['platform' => 'google', 'campaign' => $campaign->id]) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'google']) }}";
        var method = 'PUT';
    </script>
    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
