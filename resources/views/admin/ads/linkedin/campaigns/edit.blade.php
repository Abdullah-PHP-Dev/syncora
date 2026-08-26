@extends('layouts.app')

@section('title', 'Edit LinkedIn Campaign')

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
        background: #0a66c2;
        color: #fff;
        box-shadow: 0 5px 15px rgba(10, 102, 194, .3);
    }

    .step:hover {
        transform: translateY(-2px);
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
        color: #0a66c2;
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
        background: #0a66c2;
        color: white;
        padding: 15px;
        font-weight: 600;
    }

    /* ------------------------------------------------------------------
       LinkedIn ad unit preview - see create.blade.php's identical block
       for the full rationale. Kept in sync with that file since both
       need to render the same real LinkedIn ad look.
       ------------------------------------------------------------------ */
    .linkedin-preview {
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    .li-post {
        background: #fff;
    }

    .li-post-header {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 12px 16px 8px;
    }

    .li-logo {
        width: 48px;
        height: 48px;
        border-radius: 4px;
        background: #0a66c2;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        flex-shrink: 0;
        overflow: hidden;
    }

    .li-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .li-header-text {
        flex: 1;
        min-width: 0;
    }

    .li-company-name {
        font-weight: 600;
        font-size: 14px;
        color: rgba(0, 0, 0, .9);
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .li-meta {
        font-size: 12px;
        color: rgba(0, 0, 0, .6);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .li-more {
        color: rgba(0, 0, 0, .6);
        font-size: 18px;
        line-height: 1;
        padding: 4px;
    }

    .li-commentary {
        padding: 0 16px 12px;
        font-size: 14px;
        color: rgba(0, 0, 0, .9);
        line-height: 1.4;
        white-space: pre-line;
        word-break: break-word;
    }

    .li-media {
        width: 100%;
        background: #000;
        line-height: 0;
    }

    .li-media img,
    .li-media video {
        width: 100%;
        display: block;
        max-height: 320px;
        object-fit: cover;
    }

    .li-media-placeholder {
        width: 100%;
        aspect-ratio: 1.91 / 1;
        background: #eef3f8;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #90a4c0;
        font-size: 42px;
    }

    .li-cta-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: #f3f2ef;
        border-top: 1px solid #e0e0e0;
        padding: 10px 16px;
    }

    .li-cta-text {
        min-width: 0;
    }

    .li-cta-headline {
        font-weight: 600;
        font-size: 14px;
        color: rgba(0, 0, 0, .9);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 220px;
    }

    .li-cta-domain {
        font-size: 12px;
        color: rgba(0, 0, 0, .6);
        text-transform: uppercase;
        letter-spacing: .2px;
    }

    .li-cta-btn {
        flex-shrink: 0;
        background: transparent;
        border: 1.5px solid #0a66c2;
        color: #0a66c2;
        font-weight: 600;
        font-size: 14px;
        padding: 6px 16px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .li-social-bar {
        display: flex;
        justify-content: space-around;
        padding: 6px 8px;
        border-top: 1px solid #e0e0e0;
        color: rgba(0, 0, 0, .6);
        font-size: 12px;
    }

    .li-social-bar span {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 6px;
    }

    .li-text-ad {
        display: flex;
        gap: 10px;
        padding: 12px 16px;
        align-items: flex-start;
    }

    .li-text-ad-thumb {
        width: 100px;
        height: 100px;
        border-radius: 4px;
        background: #eef3f8;
        color: #90a4c0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        flex-shrink: 0;
    }

    .li-text-ad-copy {
        min-width: 0;
    }

    .li-text-ad-headline {
        font-size: 13px;
        font-weight: 600;
        color: #0a66c2;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .li-text-ad-disclaimer {
        padding: 0 16px 12px;
        font-size: 12px;
        color: rgba(0, 0, 0, .6);
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

    .locked-hint {
        font-size: 0.8rem;
        color: #6b7280;
    }
</style>

@section('content')
    <div class="col-xxl-12 mb-0">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <div class="card px-sm-6 px-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-3">
                            <a href="{{ route('admin.ads.campaigns.index', ['platform' => 'linkedin']) }}">
                                <button class="btn btn-primary btn-sm">
                                    <i class="bx bx-list-ul"></i> {{ __('admin.marketing_tools.ads.campaign.header') }}
                                </button>
                            </a>
                        </div>

                        @php
                            $adGroup = $campaign->adGroups->first();
                            $creative = $adGroup?->creatives->first();
                            // age_groups now stores every local targeting
                            // selection (not just age/seniority) - see
                            // LinkedinAdService::buildTargeting()'s
                            // $local array.
                            $ageData = json_decode($adGroup->age_groups ?? '{}', true) ?: [];
                            $selectedAgeRanges = $ageData['age_ranges'] ?? [];
                            $selectedSeniorities = $ageData['seniorities'] ?? [];
                            $selectedGenders = $ageData['genders'] ?? [];
                            $selectedCompanySizes = $ageData['company_size'] ?? [];
                            $selectedTitles = $ageData['titles'] ?? '';
                            $selectedIndustries = $ageData['industries'] ?? '';
                            $selectedSkills = $ageData['skills'] ?? '';
                            $selectedEmployers = $ageData['employers'] ?? '';
                            $selectedExperienceMin = $ageData['years_experience_min'] ?? '';
                            $selectedExperienceMax = $ageData['years_experience_max'] ?? '';
                            // location_ids stores the local Country IDs the form
                            // submitted (not LinkedIn's resolved geo URNs - see
                            // LinkedinAdService::storeAdGroup()'s comment) so the
                            // multi-select below can be pre-populated directly.
                            $selectedCountryIds = json_decode($adGroup->location_ids ?? '[]', true) ?: [];
                        @endphp

                        <div class="campaign-builder">
                            <div class="builder-header">
                                <div class="social-icon-mini linkedin">
                                    <i class="bx bxl-linkedin"></i>
                                </div>
                                <h2>Edit LinkedIn Campaign</h2>
                                <div class="campaign-steps">
                                    <div class="step active" data-step="1">Campaign</div>
                                    <div class="step" data-step="2">Budget</div>
                                    <div class="step" data-step="3">Audience</div>
                                    <div class="step" data-step="4">Creative</div>
                                    <div class="step" data-step="5">Review</div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- LEFT: Form -->
                                <div class="col-lg-8">
                                    <form id="campaign" enctype="multipart/form-data">
                                        @csrf

                                        <div class="wizard-step active" data-step="1">
                                        <div class="builder-card">
                                            <h5>Campaign Information</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Campaign Name *</label>
                                                    <input type="text" name="name" value="{{ $campaign->name }}" id="name" class="form-control" required>
                                                    <p class="error-message error-name"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Objective *</label>
                                                    <select id="objective" name="objective" class="form-control" required>
                                                        <option value="">-- Select Objective --</option>
                                                        @foreach ([
                                                            'BRAND_AWARENESS' => 'Brand Awareness',
                                                            'WEBSITE_VISIT' => 'Website Visits',
                                                            'ENGAGEMENT' => 'Engagement',
                                                            'VIDEO_VIEW' => 'Video Views',
                                                            'LEAD_GENERATION' => 'Lead Generation',
                                                            'WEBSITE_CONVERSION' => 'Website Conversions',
                                                            'JOB_APPLICANT' => 'Job Applicants',
                                                        ] as $value => $label)
                                                            <option value="{{ $value }}" @selected($campaign->objective == $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="error-message error-objective"></p>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="2">
                                        <div class="builder-card">
                                            <h5>Budget & Schedule</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Budget Mode *</label>
                                                    <select name="budget_mode" id="budget_mode" class="form-control" required>
                                                        <option value="daily" @selected(($adGroup->budget_mode ?? 'daily') == 'daily')>Daily Budget</option>
                                                        <option value="total" @selected(($adGroup->budget_mode ?? '') == 'total')>Total Budget</option>
                                                    </select>
                                                    <p class="error-message error-budget_mode"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Budget *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->metadata['currency'] ?? 'USD' }}</span>
                                                        <input class="form-control" value="{{ $adGroup->budget ?? '' }}" name="budget" id="budget" type="number" step="0.01" min="1" required>
                                                    </div>
                                                    <p class="error-message error-budget"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Start Date *</label>
                                                    <input type="date" name="start_time" value="{{ \Carbon\Carbon::parse($campaign->start_time)->format('Y-m-d') }}" id="start_time" class="form-control" required>
                                                    <p class="error-message error-start_time"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>End Date (optional)</label>
                                                    <input type="date" name="end_time" id="end_time" class="form-control" value="{{ $campaign->end_time ? \Carbon\Carbon::parse($campaign->end_time)->format('Y-m-d') : '' }}">
                                                    <p class="error-message error-end_time"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Bidding Type *</label>
                                                    <select name="bid_type" id="bid_type" class="form-control" required>
                                                        <option value="CPC" @selected(($adGroup->bid_type ?? 'CPC') == 'CPC')>Cost Per Click (CPC)</option>
                                                        <option value="CPM" @selected(($adGroup->bid_type ?? '') == 'CPM')>Cost Per 1,000 Impressions (CPM)</option>
                                                    </select>
                                                    <p class="error-message error-bid_type"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Bid Amount (optional)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->metadata['currency'] ?? 'USD' }}</span>
                                                        <input class="form-control" value="{{ $adGroup->bid_price ?? '' }}" name="bid_amount" id="bid_amount" type="number" step="0.01" min="0.01">
                                                    </div>
                                                    <p class="error-message error-bid_amount"></p>
                                                </div>
                                            </div>

                                            <!-- VAT Summary -->
                                            <div class="row mt-4">
                                                <div class="col-md-6 offset-md-6">
                                                    <div class="card shadow-sm border-0">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span>Budget</span>
                                                                <strong>{{ $account->metadata['currency'] ?? 'USD' }} <span id="budget_amount">0.00</span></strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2 text-muted">
                                                                <span>VAT (15%)</span>
                                                                <strong>{{ $account->metadata['currency'] ?? 'USD' }} <span id="vat_amount">0.00</span></strong>
                                                            </div>
                                                            <hr>
                                                            <input type="hidden" name="final_budget" id="final_budget" value="{{ $adGroup->budget ?? '' }}">
                                                            <div class="d-flex justify-content-between">
                                                                <h5 class="mb-0">Total Budget</h5>
                                                                <h5 class="mb-0 text-primary">{{ $account->metadata['currency'] ?? 'USD' }} <span id="total_budget">0.00</span></h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="3">
                                        <div class="builder-card">
                                            <h5>Audience Targeting</h5>
                                            <div class="alert alert-warning" role="alert">
                                                LinkedIn tools may not be used to discriminate based on personal characteristics like gender, age, race, or ethnicity. <a href="https://www.linkedin.com/help/linkedin/answer/86856" target="_blank" rel="noopener">Learn more</a>.
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Locations (multiple) *</label>
                                                    <select name="countries[]" id="countries" multiple class="form-control" required>
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->id }}" @selected(in_array($country->id, $selectedCountryIds))>{{ $country->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="error-message error-countries"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Age Range</label>
                                                    <div class="checkbox-group">
                                                        @foreach (['18-24' => '18 – 24', '25-34' => '25 – 34', '35-54' => '35 – 54', '55+' => '55+'] as $value => $label)
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="{{ $value }}" id="age_{{ $loop->index }}" @checked(in_array($value, $selectedAgeRanges))>
                                                                <label class="form-check-label" for="age_{{ $loop->index }}">{{ $label }}</label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-age_range"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Gender</label>
                                                    <div class="checkbox-group">
                                                        @foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label)
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="genders[]" value="{{ $value }}" id="gender_{{ $value }}" @checked(in_array($value, $selectedGenders))>
                                                                <label class="form-check-label" for="gender_{{ $value }}">{{ $label }}</label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-genders"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Years of Experience</label>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <select name="years_experience_min" id="years_experience_min" class="form-control">
                                                                <option value="">Min</option>
                                                                @for ($i = 1; $i <= 12; $i++)
                                                                    <option value="{{ $i }}" @selected((string) $selectedExperienceMin === (string) $i)>{{ $i }}{{ $i === 12 ? '+' : '' }} yr</option>
                                                                @endfor
                                                            </select>
                                                            <p class="error-message error-years_experience_min"></p>
                                                        </div>
                                                        <div class="col-6">
                                                            <select name="years_experience_max" id="years_experience_max" class="form-control">
                                                                <option value="">Max</option>
                                                                @for ($i = 1; $i <= 12; $i++)
                                                                    <option value="{{ $i }}" @selected((string) $selectedExperienceMax === (string) $i)>{{ $i }}{{ $i === 12 ? '+' : '' }} yr</option>
                                                                @endfor
                                                            </select>
                                                            <p class="error-message error-years_experience_max"></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <label>Seniority</label>
                                                    <div class="platform-group">
                                                        @foreach (['entry' => 'Entry', 'senior' => 'Senior', 'manager' => 'Manager', 'director' => 'Director', 'vp' => 'VP', 'cxo' => 'CXO', 'partner' => 'Partner', 'owner' => 'Owner'] as $value => $label)
                                                            <div class="platform-card">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input platform-switch" type="checkbox" name="seniorities[]" value="{{ $value }}" id="seniority_{{ $value }}" @checked(in_array($value, $selectedSeniorities))>
                                                                    <label class="form-check-label ms-2" for="seniority_{{ $value }}">{{ $label }}</label>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-seniorities"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <label>Job Titles (comma-separated)</label>
                                                    <p class="text-muted mb-1" style="font-size:0.8rem;">LinkedIn can't combine Job Titles with Seniority in the same campaign - if both are filled in, Job Titles takes priority and Seniority is ignored.</p>
                                                    <input type="text" name="titles" id="titles" class="form-control" value="{{ $selectedTitles }}" placeholder="e.g. Marketing Manager, VP of Sales">
                                                    <p class="error-message error-titles"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Industries (comma-separated)</label>
                                                    <input type="text" name="industries" id="industries" class="form-control" value="{{ $selectedIndustries }}" placeholder="e.g. Computer Software, Biotechnology">
                                                    <p class="error-message error-industries"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Company Size</label>
                                                    <div class="platform-group">
                                                        @foreach (['1' => '1', '2-10' => '2-10', '11-50' => '11-50', '51-200' => '51-200', '201-500' => '201-500', '501-1000' => '501-1,000', '1001-5000' => '1,001-5,000', '5001-10000' => '5,001-10,000', '10001+' => '10,001+'] as $value => $label)
                                                            <div class="platform-card">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input platform-switch" type="checkbox" name="company_size[]" value="{{ $value }}" id="company_size_{{ $loop->index }}" @checked(in_array($value, $selectedCompanySizes))>
                                                                    <label class="form-check-label ms-2" for="company_size_{{ $loop->index }}">{{ $label }}</label>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-company_size"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <label>Company Names (comma-separated)</label>
                                                    <p class="text-muted mb-1" style="font-size:0.8rem;">LinkedIn can't combine Company Names with Industries or Company Size in the same campaign - if any are filled in together, Company Names takes priority.</p>
                                                    <input type="text" name="employers" id="employers" class="form-control" value="{{ $selectedEmployers }}" placeholder="e.g. Microsoft, Salesforce">
                                                    <p class="error-message error-employers"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <label>Skills (comma-separated)</label>
                                                    <input type="text" name="skills" id="skills" class="form-control" value="{{ $selectedSkills }}" placeholder="e.g. Project Management, SQL">
                                                    <p class="error-message error-skills"></p>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="4">
                                        <div class="builder-card">
                                            <h5>Creative</h5>
                                            <p class="locked-hint">LinkedIn Creatives can't be edited once created - the ad format, media and text below are locked. To change them, delete this campaign and create a new one.</p>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Ad Format</label>
                                                    <select id="creative_type" class="form-control" disabled>
                                                        <option value="SPONSORED_CONTENT" @selected(($creative->type ?? '') == 'SPONSORED_CONTENT')>Sponsored Content (image/video, shown in-feed)</option>
                                                        <option value="TEXT_AD" @selected(($creative->type ?? '') == 'TEXT_AD')>Text Ad (small sidebar text ad)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Call to Action</label>
                                                    <select name="call_to_action" id="call_to_action" class="form-control">
                                                        @foreach ([
                                                            '' => '-- None --', 'LEARN_MORE' => 'Learn More', 'APPLY' => 'Apply', 'DOWNLOAD' => 'Download',
                                                            'SIGN_UP' => 'Sign Up', 'SUBSCRIBE' => 'Subscribe', 'REGISTER' => 'Register',
                                                            'JOIN' => 'Join', 'ATTEND' => 'Attend', 'REQUEST_DEMO' => 'Request Demo',
                                                        ] as $value => $label)
                                                            <option value="{{ $value }}" @selected(($creative->call_to_action ?? '') == $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="error-message error-call_to_action"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <label>Landing Page URL</label>
                                                    <input type="url" id="target_link" class="form-control" value="{{ $creative->url ?? '' }}" disabled>
                                                </div>
                                            </div>

                                            @if (($creative->type ?? '') !== 'TEXT_AD')
                                                <div class="mt-4">
                                                    @php $existingMedia = $creative?->media->first(); @endphp
                                                    @if ($existingMedia)
                                                        <label>Current Media</label>
                                                        <div class="upload-zone">
                                                            @if ($existingMedia->type === 'VIDEO')
                                                                <video src="{{ $existingMedia->url }}" controls style="max-width:100%;border-radius:12px;"></video>
                                                            @else
                                                                <img src="{{ $existingMedia->url }}" style="max-width:100%;border-radius:12px;">
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="mt-4">
                                                <label>Ad Text</label>
                                                <textarea id="ad_description" rows="4" class="form-control" maxlength="600" disabled>{{ $creative->message ?? '' }}</textarea>
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

                                <!-- RIGHT: Preview - mirrors the real LinkedIn ad unit, see
                                     create.blade.php's identical block for the rationale. -->
                                @php
                                    $isTextAd = ($creative->type ?? '') === 'TEXT_AD';
                                    $existingMedia = $creative?->media->first();
                                    $previewDomain = 'yourwebsite.com';
                                    if (!empty($creative->url)) {
                                        $host = parse_url($creative->url, PHP_URL_HOST);
                                        $previewDomain = $host ? preg_replace('/^www\./', '', $host) : $previewDomain;
                                    }
                                @endphp
                                <div class="col-lg-4">
                                    <div class="preview-card">
                                        <div class="preview-header">Live Preview</div>
                                        <div class="linkedin-preview" id="sponsoredPreview" style="{{ $isTextAd ? 'display:none' : '' }}">
                                            <div class="li-post">
                                                <div class="li-post-header">
                                                    <div class="li-logo">{{ strtoupper(substr($account->name ?? 'C', 0, 1)) }}</div>
                                                    <div class="li-header-text">
                                                        <div class="li-company-name">{{ $account->name ?? 'Company' }}</div>
                                                        <div class="li-meta"><span>Promoted</span></div>
                                                    </div>
                                                    <div class="li-more">&#8226;&#8226;&#8226;</div>
                                                </div>
                                                <div class="li-commentary" id="previewDescription">{{ $creative->message ?? 'Ad text will appear here...' }}</div>
                                                <div class="li-media">
                                                    <img id="previewImage" style="{{ $existingMedia && $existingMedia->type !== 'VIDEO' ? '' : 'display:none' }}" src="{{ $existingMedia->url ?? '' }}">
                                                    <video id="previewVideo" controls style="{{ $existingMedia && $existingMedia->type === 'VIDEO' ? '' : 'display:none' }}" src="{{ $existingMedia && $existingMedia->type === 'VIDEO' ? $existingMedia->url : '' }}"></video>
                                                    <div class="li-media-placeholder" id="previewMediaPlaceholder" style="{{ $existingMedia ? 'display:none' : '' }}"><i class="bx bx-image"></i></div>
                                                </div>
                                                <div class="li-cta-bar">
                                                    <div class="li-cta-text">
                                                        <div class="li-cta-headline" id="previewTitle">{{ $campaign->name }}</div>
                                                        <div class="li-cta-domain" id="previewDomain">{{ $previewDomain }}</div>
                                                    </div>
                                                    <a id="previewCTA" href="{{ $creative->url ?? '#' }}" target="_blank" class="li-cta-btn">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $creative->call_to_action ?? 'Learn more')) }}</a>
                                                </div>
                                                <div class="li-social-bar">
                                                    <span><i class="bx bx-like"></i> Like</span>
                                                    <span><i class="bx bx-comment"></i> Comment</span>
                                                    <span><i class="bx bx-repost"></i> Repost</span>
                                                    <span><i class="bx bx-send"></i> Send</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="linkedin-preview" id="textAdPreview" style="{{ $isTextAd ? '' : 'display:none' }}">
                                            <div class="li-text-ad">
                                                <div class="li-text-ad-thumb"><i class="bx bxl-linkedin"></i></div>
                                                <div class="li-text-ad-copy">
                                                    <div class="li-text-ad-headline" id="textAdTitle">{{ $campaign->name }}</div>
                                                </div>
                                            </div>
                                            <div class="li-text-ad-disclaimer">Promoted</div>
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
        window.addEventListener('load', function() {

        function beautifyLabel(value) {
            return value.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        if ($.fn.select2) {
            $('#countries').select2();
        }

        // ------------------------------------------------------------------
        // BUDGET CALCULATOR
        // ------------------------------------------------------------------
        function calculateBudget() {
            let budgetMode = document.getElementById('budget_mode')?.value;
            let budget = parseFloat(document.getElementById('budget')?.value) || 0;
            let startDate = document.getElementById('start_time')?.value;
            let endDate = document.getElementById('end_time')?.value;
            let allocatedBudget = budget;

            if (budgetMode === 'daily' && startDate && endDate) {
                let start = new Date(startDate);
                let end = new Date(endDate);
                let days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                allocatedBudget = days > 0 ? budget * days : 0;
            }

            // Same 15% VAT overlay as the Facebook/Snapchat builders - a
            // locally-tracked cost estimate (final_budget), not sent to
            // LinkedIn's API itself (updateAdGroup() sends the raw
            // `budget` field to dailyBudget/totalBudget, VAT-free).
            let vat = allocatedBudget * 0.15;
            let total = allocatedBudget + vat;

            if (document.getElementById('budget_amount')) document.getElementById('budget_amount').innerText = allocatedBudget.toFixed(2);
            if (document.getElementById('vat_amount')) document.getElementById('vat_amount').innerText = vat.toFixed(2);
            if (document.getElementById('total_budget')) document.getElementById('total_budget').innerText = total.toFixed(2);
            if (document.getElementById('final_budget')) document.getElementById('final_budget').value = total.toFixed(2);
        }

        document.getElementById('budget')?.addEventListener('input', calculateBudget);
        document.getElementById('budget_mode')?.addEventListener('change', calculateBudget);
        document.getElementById('start_time')?.addEventListener('change', calculateBudget);
        document.getElementById('end_time')?.addEventListener('change', calculateBudget);
        calculateBudget();

        // ------------------------------------------------------------------
        // LIVE PREVIEW
        // ------------------------------------------------------------------
        document.getElementById('name')?.addEventListener('keyup', function() {
            const title = this.value || 'Campaign Name';
            if (document.getElementById('previewTitle')) document.getElementById('previewTitle').innerText = title;
            if (document.getElementById('textAdTitle')) document.getElementById('textAdTitle').innerText = title;
        });

        const callToActionSelect = document.getElementById('call_to_action');
        const previewCTA = document.getElementById('previewCTA');

        callToActionSelect?.addEventListener('change', function() {
            let text = this.value ? (this.options[this.selectedIndex]?.text || 'Learn more') : 'Learn more';
            if (previewCTA) previewCTA.innerText = text;
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
                if (field.disabled) continue;
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
            let countries = $('#countries option:selected').map(function() { return this.text; }).get().join(', ');
            let seniorities = Array.from(document.querySelectorAll('input[name="seniorities[]"]:checked'))
                .map(el => el.nextElementSibling.innerText.trim()).join(', ');
            let ageRanges = Array.from(document.querySelectorAll('input[name="age_range[]"]:checked'))
                .map(el => el.nextElementSibling.innerText.trim()).join(', ');
            let genders = Array.from(document.querySelectorAll('input[name="genders[]"]:checked'))
                .map(el => el.nextElementSibling.innerText.trim()).join(', ');
            let companySizes = Array.from(document.querySelectorAll('input[name="company_size[]"]:checked'))
                .map(el => el.nextElementSibling.innerText.trim()).join(', ');
            let experience = [document.getElementById('years_experience_min').value, document.getElementById('years_experience_max').value]
                .filter(Boolean).join(' - ');

            let html = '';
            html += reviewRow('Campaign Name', document.getElementById('name').value);
            html += reviewRow('Objective', beautifyLabel(document.getElementById('objective').value || ''));
            html += reviewRow('Budget Mode', beautifyLabel(document.getElementById('budget_mode').value || ''));
            html += reviewRow('Total Budget', document.getElementById('total_budget').innerText);
            html += reviewRow('Start Date', document.getElementById('start_time').value);
            html += reviewRow('End Date', document.getElementById('end_time').value);
            html += reviewRow('Bidding Type', document.getElementById('bid_type').value);
            html += reviewRow('Call To Action', beautifyLabel(callToActionSelect.value || ''));
            html += reviewRow('Locations', countries);
            html += reviewRow('Age Range', ageRanges);
            html += reviewRow('Gender', genders);
            html += reviewRow('Years of Experience', experience);
            html += reviewRow('Seniority', seniorities);
            html += reviewRow('Job Titles', document.getElementById('titles').value);
            html += reviewRow('Industries', document.getElementById('industries').value);
            html += reviewRow('Company Size', companySizes);
            html += reviewRow('Company Names', document.getElementById('employers').value);
            html += reviewRow('Skills', document.getElementById('skills').value);

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

        var url = "{{ route('admin.ads.campaigns.update', ['platform' => 'linkedin', 'campaign' => $campaign->id]) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'linkedin']) }}";
        var method = 'PUT';
    </script>
    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
