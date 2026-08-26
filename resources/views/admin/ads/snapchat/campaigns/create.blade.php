@extends('layouts.app')

@section('title', 'Create snapchat Campaign')

<style>
    /* Your existing styles remain unchanged */
    .campaign-builder {
        max-width: 1400px;
        margin: auto;
    }

    .builder-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .meta-logo {
        font-size: 42px;
    }

    .meta-logo i:first-child {
        color: #1877F2;
    }

    .meta-logo i:last-child {
        color: #E1306C;
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
        background: #1877F2;
        color: #fff;
        box-shadow: 0 5px 15px rgba(24, 119, 242, .3);
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

    .platform-card.active {
        border-color: #1877F2;
        background: #f0f7ff;
        box-shadow: 0 4px 15px rgba(24, 119, 242, .12);
    }

    .platform-card i {
        font-size: 24px;
        vertical-align: middle;
    }

    .platform-switch {
        cursor: pointer;
    }

    .duration-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .duration-btn {
        border: none;
        background: #eef3ff;
        padding: 10px 20px;
        border-radius: 10px;
    }

    .duration-btn.active {
        background: #1877F2;
        color: white;
    }

    .upload-zone {
        border: 2px dashed #d8dce3;
        border-radius: 15px;
        padding: 50px;
        text-align: center;
    }

    .upload-zone i {
        font-size: 50px;
        color: #1877F2;
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
        background: #1877F2;
        color: white;
        padding: 15px;
        font-weight: 600;
    }

    .facebook-preview {
        padding: 15px;
    }

    .preview-top {
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

    .preview-image {
        width: 100%;
        margin: 15px 0;
        border-radius: 12px;
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
                            <a href="{{ route('admin.ads.campaigns.index', ['platform' => 'snapchat']) }}">
                                <button class="btn btn-primary btn-sm">
                                    <i class="bx bx-list-ul"></i> {{ __('admin.marketing_tools.ads.campaign.header') }}
                                </button>
                            </a>
                        </div>

                        <div class="campaign-builder">
                            <div class="builder-header">
                                <div class="social-icon-mini snapchat">
                                    <i class="bx bxl-snapchat"></i>
                                </div>
                                <h2>Create Snapchat Campaign</h2>
                                <div class="campaign-steps">
                                    <div class="step active" data-step="1">Campaign</div>
                                    <div class="step" data-step="2">Budget</div>
                                    <div class="step" data-step="3">Goal</div>
                                    <div class="step" data-step="4">Creative</div>
                                    <div class="step" data-step="5">Audience</div>
                                    <div class="step" data-step="6">Review</div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- LEFT: Form -->
                                <div class="col-lg-8">
                                    <form id="campaign" enctype="multipart/form-data">
                                        @csrf

                                        <div class="wizard-step active" data-step="1">
                                        <!-- ============================================================ -->
                                        <!-- 1. CAMPAIGN INFORMATION                                       -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Campaign Information</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Campaign Name *</label>
                                                    <input type="text" name="name" id="name" class="form-control"
                                                        required>
                                                    <p class="error-message error-name"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Objective *</label>
                                                    <select id="objective" name="objective" class="form-control" required>
                                                        <option value="">-- Select Objective --</option>
                                                        <option value="AWARENESS_AND_ENGAGEMENT">Awareness & Engagement
                                                        </option>
                                                        <option value="APP_PROMOTION">App Promotion</option>
                                                        <option value="SALES">Sales</option>
                                                        <option value="LEADS">Leads</option>
                                                        <option value="TRAFFIC">Traffic</option>
                                                    </select>
                                                    <p class="error-message error-objective"></p>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="2">
                                        <!-- ============================================================ -->
                                        <!-- 2. BUDGET & SCHEDULE (AdGroup level)                        -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Budget & Schedule</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Budget Mode *</label>
                                                    <select name="budget_mode" id="budget_mode" class="form-control"
                                                        required>
                                                        <option value="daily">Daily Budget</option>
                                                        <option value="life_time">Lifetime Budget</option>
                                                    </select>
                                                    <p class="error-message error-budget_mode"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Budget *</label>
                                                    <div class="input-group">
                                                        <span
                                                            class="input-group-text">{{ $account->metadata['currency'] ?? 'USD' }}</span>
                                                        <input class="form-control" name="budget" id="budget"
                                                            type="number" step="0.01" min="1" required>
                                                    </div>
                                                    <p class="error-message error-budget"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Start Date *</label>
                                                    <input type="date" name="start_time" id="start_time"
                                                        class="form-control" required>
                                                    <p class="error-message error-start_time"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>End Date (optional)</label>
                                                    <input type="date" name="end_time" id="end_time"
                                                        class="form-control">
                                                    <p class="error-message error-end_time"></p>
                                                </div>
                                            </div>

                                            <!-- VAT Summary (unchanged) -->
                                            <div class="row mt-4">
                                                <div class="col-md-6 offset-md-6">
                                                    <div class="card shadow-sm border-0">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span>Budget</span>
                                                                <strong>{{ $account->metadata['currency'] ?? 'USD' }} <span
                                                                        id="budget_amount">0.00</span></strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2 text-muted">
                                                                <span>VAT (15%)</span>
                                                                <strong>{{ $account->metadata['currency'] ?? 'USD' }} <span
                                                                        id="vat_amount">0.00</span></strong>
                                                            </div>
                                                            <hr>
                                                            <input type="hidden" name="final_budget" id="final_budget"
                                                                value="">
                                                            <div class="d-flex justify-content-between">
                                                                <h5 class="mb-0">Total Budget</h5>
                                                                <h5 class="mb-0 text-primary">
                                                                    {{ $account->metadata['currency'] ?? 'USD' }} <span
                                                                        id="total_budget">0.00</span></h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="3">
                                        <!-- ============================================================ -->
                                        <!-- 3. GOAL SETUP (AdGroup level)                               -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Goal Setup</h5>
                                            <div class="row">
                                                <!-- Promotion Type -->
                                                <div class="col-md-6 promotion-type-block">
                                                    <label>Promotion Type *</label>
                                                    <select name="promotion_type" id="promotion_type" class="form-control"
                                                        required>
                                                        <option value="">-- Select Promotion Type --</option>
                                                        <!-- Options will be populated based on objective -->
                                                    </select>
                                                    <p class="error-message error-promotion_type"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Conversion Location *</label>
                                                    <select name="conversion_location" id="conversion_location"
                                                        class="form-control" required>
                                                        <option value="">-- Select Promotion Type First --</option>
                                                    </select>
                                                    <p class="error-message error-conversion_location"></p>
                                                </div>

                                            </div>
                                            <br>
                                            <div class="row">
                                                <!-- Biding Strategy -->
                                                <div class="col-md-6">
                                                    <label>Biding Strategy *</label>
                                                    <select name="bid_strategy" id="bid_strategy" class="form-control"
                                                        required>
                                                        <option value="">-- Select Biding Strategy --</option>
                                                    </select>
                                                    <p class="error-message error-bid_strategy"></p>
                                                </div>
                                                <!-- Optimization Goal -->
                                                <div class="col-md-6">
                                                    <label>Optimization Goal *</label>
                                                    <select name="optimization_goal" id="optimization_goal"
                                                        class="form-control" required>
                                                        <option value="">-- Select Optimization Goal --</option>
                                                    </select>
                                                    <p class="error-message error-optimization_goal"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row mt-3">
                                                <!-- Billing Event (for LEAD_GENERATION) -->
                                                <div class="col-md-6">
                                                    <label>Billing Event</label>
                                                    <select name="billing_event" id="billing_event" class="form-control">
                                                    </select>
                                                    <p class="error-message error-billing_event"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Pixel ID</label>
                                                    <input type="text" name="pixel_id" id="pixel_id"
                                                        class="form-control">
                                                    <p class="error-message error-pixel_id"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row bid_amount" style="display:none">
                                                <div class="col-md-4">
                                                    <label>Bid Amount</label>
                                                    <div class="input-group">
                                                        <span
                                                            class="input-group-text">{{ $account->metadata['currency'] ?? 'USD' }}</span>
                                                        <input class="form-control" name="bid_amount" id="bid_amount"
                                                            type="number" step="0.01">
                                                    </div>
                                                    <p class="error-message error-bid_amount"></p>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="4">
                                        <!-- ============================================================ -->
                                        <!-- 4. AD CREATIVE (Ad level)                                   -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Creative</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Creative Type</label>
                                                    <select name="creative_type" id="creative_type" class="form-control">
                                                        <option value="SNAP_AD">Snap Ad</option>
                                                        <option value="APP_INSTALL">App Install</option>
                                                        <option value="LENS_APP_INSTALL">Lens App Install</option>
                                                        <option value="DEEP_LINK">Deep Link</option>
                                                        <option value="LEAD_GENERATION">Lead Generation</option>
                                                        <option value="LENS_DEEP_LINK">Lens Deep Link</option>
                                                        <option value="WEB_VIEW">Web View</option>
                                                        <option value="LENS_WEB_VIEW">Lens Web View</option>
                                                        <option value="AD_TO_MESSAGE">Ad To Message</option>
                                                        <option value="AD_TO_LENS">Ad To Lens</option>
                                                        <option value="AD_TO_CALL">Ad To Call</option>
                                                        <option value="REMINDER">Reminder</option>
                                                    </select>
                                                    <p class="error-message error-creative_type"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Call to Action</label>
                                                    <select name="call_to_action" id="call_to_action"
                                                        class="form-control">
                                                        <option value="">-- Select CTA --</option>
                                                        <option value="LEARN_MORE">Learn More</option>
                                                        <option value="SHOP_NOW">Shop Now</option>
                                                        <option value="SIGN_UP">Sign Up</option>
                                                        <option value="BOOK_NOW">Book Now</option>
                                                        <option value="CONTACT_US">Contact Us</option>
                                                        <option value="CALL_NOW">Call Now</option>
                                                        <option value="SEND_MESSAGE">Send Message</option>
                                                        <option value="DOWNLOAD">Download</option>
                                                    </select>
                                                    <p class="error-message error-call_to_action"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Target URL (Landing Page)</label>
                                                    <input type="url" name="target_link" id="target_link"
                                                        class="form-control" placeholder="https://example.com">
                                                    <p class="error-message error-target_link"></p>
                                                </div>
                                            </div>

                                            <!-- App Install / Deep Link properties -->
                                            <div class="row mt-3 creative-app-fields" style="display:none">
                                                <div class="col-md-4">
                                                    <label>App Name</label>
                                                    <input type="text" name="app_name" id="app_name" class="form-control" maxlength="30">
                                                    <p class="error-message error-app_name"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>iOS App ID</label>
                                                    <input type="text" name="ios_app_id" id="ios_app_id" class="form-control">
                                                    <p class="error-message error-ios_app_id"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Android App URL</label>
                                                    <input type="text" name="android_app_url" id="android_app_url" class="form-control" placeholder="https://play.google.com/store/apps/details?id=...">
                                                    <p class="error-message error-android_app_url"></p>
                                                </div>
                                                <div class="col-md-6 mt-3">
                                                    <label>App Icon Media ID</label>
                                                    <input type="text" name="icon_media_id" id="icon_media_id" class="form-control" placeholder="Media ID from Snapchat Ads Manager">
                                                    <p class="error-message error-icon_media_id"></p>
                                                </div>
                                            </div>

                                            <!-- Deep Link only -->
                                            <div class="row mt-3 creative-deeplink-fields" style="display:none">
                                                <div class="col-md-6">
                                                    <label>Deep Link URI</label>
                                                    <input type="text" name="deep_link_uri" id="deep_link_uri" class="form-control" placeholder="myapp://path">
                                                    <p class="error-message error-deep_link_uri"></p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label>Fallback Type</label>
                                                    <select name="fallback_type" id="fallback_type" class="form-control">
                                                        <option value="APP_INSTALL">App Install</option>
                                                        <option value="WEB_SITE">Website</option>
                                                    </select>
                                                    <p class="error-message error-fallback_type"></p>
                                                </div>
                                                <div class="col-md-3">
                                                    <label>Web Fallback URL</label>
                                                    <input type="url" name="web_view_fallback_url" id="web_view_fallback_url" class="form-control">
                                                    <p class="error-message error-web_view_fallback_url"></p>
                                                </div>
                                            </div>

                                            <!-- Ad to Call / Ad to Message -->
                                            <div class="row mt-3 creative-phone-fields" style="display:none">
                                                <div class="col-md-6">
                                                    <label>Phone Number ID</label>
                                                    <input type="text" name="phone_number_id" id="phone_number_id" class="form-control" placeholder="Registered phone number ID">
                                                    <p class="error-message error-phone_number_id"></p>
                                                </div>
                                                <div class="col-md-6 creative-message-field" style="display:none">
                                                    <label>Message (optional)</label>
                                                    <input type="text" name="message" id="message" class="form-control" maxlength="160">
                                                    <p class="error-message error-message"></p>
                                                </div>
                                            </div>

                                            <!-- Ad to Lens -->
                                            <div class="row mt-3 creative-lens-fields" style="display:none">
                                                <div class="col-md-6">
                                                    <label>Lens Media ID</label>
                                                    <input type="text" name="lens_media_id" id="lens_media_id" class="form-control">
                                                    <p class="error-message error-lens_media_id"></p>
                                                </div>
                                            </div>

                                            <br>
                                            <div class="duration-buttons">
                                                <input type="hidden" name="media_type" id="media_type" value="IMAGE">
                                                <input type="hidden" name="carousel_cards" id="carousel_cards" value="[]">
                                                <button type="button" class="duration-btn media-type active"
                                                    data-type="IMAGE">Image</button>
                                                <button type="button" class="duration-btn media-type"
                                                    data-type="CAROUSEL">Carousel</button>
                                                <button type="button" class="duration-btn media-type"
                                                    data-type="VIDEO">Video</button>
                                                <p class="error-message error-media_type"></p>
                                            </div>
                                            <br>
                                            <div class="upload-zone">
                                                <i class="bx bx-cloud-upload"></i>
                                                <h6>Drag & Drop Media</h6>
                                                <p id="uploadHint">Upload image (max 30MB) or video (max 500MB). Snapchat generates the video thumbnail automatically.</p>
                                                <input type="file" name="media[]" id="mediaInput" hidden
                                                    accept="image/*,video/*">
                                                <button type="button" class="btn btn-primary"
                                                    onclick="document.getElementById('mediaInput').click()">Upload
                                                    Media</button>
                                                <p class="error-message error-media"></p>
                                            </div>
                                            <div class="mt-4">
                                                <label>Description (headline, max 34 chars)</label>
                                                <textarea id="ad_description" name="description" rows="4" class="form-control" maxlength="34"></textarea>
                                                <p class="error-message error-description"></p>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="5">
                                        <!-- ============================================================ -->
                                        <!-- 5. AUDIENCE TARGETING (AdGroup level)                       -->
                                        <!-- ============================================================ -->
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
                                                            <input class=" form-check-input platform-switch"
                                                                type="checkbox" name="age_range[]" value="13-17"
                                                                id="age_13-17">
                                                            <label class="form-check-label" for="age_13-17">13 –
                                                                17</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch"
                                                                type="checkbox" name="age_range[]" value="18-20"
                                                                id="age_18_20">
                                                            <label class="form-check-label" for="age_18_20">18 –
                                                                20</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch"
                                                                type="checkbox" name="age_range[]" value="21-24"
                                                                id="age_21_24">
                                                            <label class="form-check-label" for="age_21_24">21 –
                                                                24</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch"
                                                                type="checkbox" name="age_range[]" value="25-34"
                                                                id="age_25_34">
                                                            <label class="form-check-label" for="age_25_34">25 –
                                                                34</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch"
                                                                type="checkbox" name="age_range[]" value="35-44"
                                                                id="age_35_44">
                                                            <label class="form-check-label" for="age_35_44">35 – 44</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch"
                                                                type="checkbox" name="age_range[]" value="45-54"
                                                                id="age_45_54">
                                                            <label class="form-check-label" for="age_45_54">45 – 54</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch"
                                                                type="checkbox" name="age_range[]" value="55+"
                                                                id="age_55_plus">
                                                            <label class="form-check-label" for="age_55_plus">55+</label>
                                                        </div>
                                                    </div>
                                                    <p class="error-message error-age_range"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Countries (multiple)</label>
                                                    <select name="countries[]" id="countries" multiple
                                                        class="form-control">
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->id }}">{{ $country->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <p class="error-message error-countries"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <label>Languages</label>
                                                    <div class="platform-group">
                                                        @foreach (['en' => 'English', 'ar' => 'Arabic', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'ja' => 'Japanese', 'ko' => 'Korean', 'pt' => 'Portuguese', 'ru' => 'Russian', 'zh' => 'Chinese'] as $code => $name)
                                                            <div class="platform-card">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input platform-switch"
                                                                        type="checkbox" name="languages[]"
                                                                        value="{{ $code }}"
                                                                        id="lang_{{ $code }}"
                                                                        {{ $code == 'en' ? 'checked' : '' }}>
                                                                    <label class="form-check-label ms-2"
                                                                        for="lang_{{ $code }}">{{ $name }}</label>
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
                                                <p class="text-muted">Please review your campaign details before launching.</p>
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

                                <!-- RIGHT: Preview (unchanged mostly) -->
                                <div class="col-lg-4">
                                    <div class="preview-card">
                                        <div class="preview-header">Live Preview</div>
                                        <div class="facebook-preview">
                                            <div class="preview-top">
                                                <div class="avatar"></div>
                                                <div>
                                                    <strong>{{ $account->name ?? 'Brand' }}</strong>
                                                    <div>Sponsored</div>
                                                </div>
                                            </div>
                                            <img id="previewImage" class="preview-image" style="display:none">
                                            <video id="previewVideo" class="preview-image" controls
                                                style="display:none;width:100%;border-radius:12px;"></video>
                                            <div id="carouselPreview" style="display:none">
                                                <img id="carouselImage" class="preview-image">
                                                <small class="text-muted d-block mt-2">Snapchat builds a carousel from separate Snap Ad cards bundled together - each card gets its own headline below, but shares the same call-to-action and link set above.</small>
                                                <div class="mt-3"><label>Card Headline (max 34 chars)</label><input type="text"
                                                        id="carouselTitle" class="form-control" placeholder="Card headline" maxlength="34">
                                                </div>
                                                <div class="mt-3"><label>Description (internal reference)</label>
                                                    <textarea id="carouselDescription" class="form-control" rows="3" placeholder="Card description"></textarea>
                                                </div>
                                                <div class="d-flex justify-content-between mt-3">
                                                    <button type="button" class="btn btn-primary"
                                                        id="prevImage">Previous</button>
                                                    <span id="carouselCounter"></span>
                                                    <button type="button" class="btn btn-primary"
                                                        id="nextImage">Next</button>
                                                </div>
                                            </div>
                                            <div class="preview-content">
                                                <h6 id="previewTitle">Campaign Name</h6>
                                                <p id="previewDescription">Ad description will appear here...</p>
                                            </div>
                                            <a id="previewCTA" href="#" target="_blank"
                                                class="btn btn-primary w-100">Learn More</a>
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
        // The shared layout mounts a Vue 2 root on #app (resources/js/app.js:
        // `new Vue({ el: '#app' })`) with no template/render option, so Vue
        // compiles whatever server-rendered HTML is already inside #app - all
        // of this form - as an in-DOM template and re-renders it once its
        // module script finishes loading. Since that script tag is a Vite
        // `type="module"` (deferred) tag, it executes *after* this ordinary
        // inline script has already run and attached listeners to the
        // original nodes - Vue's re-render then swaps those nodes out from
        // under us, silently orphaning every listener and stale-capturing
        // every querySelector reference below (that's why Next/Previous, the
        // cascading dropdowns, media upload, etc. would otherwise look wired
        // up but do nothing on click). Deferring everything to `load` - which
        // waits for that module script to finish - runs this against the
        // DOM Vue actually leaves in place.
        window.addEventListener('load', function() {
        $('#bid_strategy').on('change', function() {
            var bidStrategy = $(this).val();
            $('.bid_amount').hide();
            if (bidStrategy == 'TARGET_COST') {
                $('.bid_amount').show();
            }
        });
        // ------------------------------------------------------------------
        // 1c. CREATIVE TYPE → CALL-TO-ACTION MAPPING
        // ------------------------------------------------------------------
        const creativeTypeCtaMap = {
            'APP_INSTALL': ['BOOK_NOW', 'DONATE', 'DOWNLOAD', 'GET_NOW', 'INSTALL_NOW', 'ORDER_NOW', 'PLAY', 'SHOP_NOW',
                'SIGN_UP', 'TRY', 'USE_APP', 'WATCH', 'VOTE', 'DIRECTIONS', 'PLAY_GAME'
            ],
            'LENS_APP_INSTALL': ['BOOK_NOW', 'DONATE', 'DOWNLOAD', 'GET_NOW', 'INSTALL_NOW', 'ORDER_NOW', 'PLAY',
                'SHOP_NOW', 'SIGN_UP', 'TRY', 'USE_APP', 'WATCH', 'PLAY_GAME'
            ],
            'DEEP_LINK': ['DONATE', 'PLAY', 'SHOP_NOW', 'SIGN_UP', 'USE_APP', 'MORE', 'OPEN_APP', 'TRY', 'WATCH',
                'VIEW_PROFILE', 'VOTE', 'DIRECTIONS', 'PRE_REGISTER', 'PLAY_GAME', 'DOWNLOAD'
            ],
            'LEAD_GENERATION': ['APPLY_NOW', 'MORE', 'BOOK_NOW', 'GET_NOW', 'SIGN_UP', 'TEST_DRIVE',
                'REQUEST_APPOINTMENT', 'REQUEST_QUOTE', 'FREE_TRIAL', 'CLAIM_SAMPLE', 'GET_COUPON'
            ],
            'LENS_DEEP_LINK': ['DONATE', 'PLAY', 'SHOP_NOW', 'SIGN_UP', 'USE_APP', 'MORE', 'OPEN_APP', 'TRY', 'WATCH',
                'VIEW_PROFILE', 'VOTE', 'DIRECTIONS', 'VIEW_MENU', 'PRE_REGISTER', 'PLAY_GAME'
            ],
            'WEB_VIEW': ['APPLY_NOW', 'MORE', 'ORDER_NOW', 'PLAY', 'READ', 'SHOP_NOW', 'SHOW', 'SIGN_UP', 'VIEW',
                'WATCH', 'DONATE', 'DOWNLOAD', 'RESPOND', 'BUY_TICKETS', 'SHOWTIMES', 'BOOK_NOW', 'GET_NOW',
                'LISTEN', 'TRY', 'VOTE', 'VIEW_MENU', 'PRE_REGISTER', 'PLAY_GAME'
            ],
            'LENS_WEB_VIEW': ['APPLY_NOW', 'MORE', 'ORDER_NOW', 'PLAY', 'READ', 'SHOP_NOW', 'SHOW', 'SIGN_UP', 'VIEW',
                'WATCH', 'DONATE', 'DOWNLOAD', 'RESPOND', 'BUY_TICKETS', 'SHOWTIMES', 'BOOK_NOW', 'GET_NOW',
                'LISTEN', 'TRY', 'VOTE', 'DIRECTIONS', 'VIEW_MENU', 'PRE_REGISTER', 'PLAY_GAME'
            ],
            'AD_TO_LENS': ['PLAY', 'TRY', 'SHOP_NOW', 'VOTE'],
            'AD_TO_MESSAGE': ['MESSAGE_NOW', 'OPEN_APP'],
            'AD_TO_CALL': ['CALL_NOW', 'OPEN_APP'],
            'REMINDER': ['REMIND_ME']
        };

        // Fields specific to certain creative types - shown/hidden below.
        const creativeTypeFieldGroups = {
            'APP_INSTALL': ['creative-app-fields'],
            'LENS_APP_INSTALL': ['creative-app-fields'],
            'DEEP_LINK': ['creative-app-fields', 'creative-deeplink-fields'],
            'LENS_DEEP_LINK': ['creative-app-fields', 'creative-deeplink-fields'],
            'AD_TO_CALL': ['creative-phone-fields'],
            'AD_TO_MESSAGE': ['creative-phone-fields', 'creative-message-field'],
            'AD_TO_LENS': ['creative-lens-fields'],
        };

        function updateCreativeTypeFields() {
            const type = document.getElementById('creative_type')?.value;
            const activeGroups = creativeTypeFieldGroups[type] || [];

            document.querySelectorAll('.creative-app-fields, .creative-deeplink-fields, .creative-phone-fields, .creative-message-field, .creative-lens-fields')
                .forEach(el => {
                    const owningGroup = [...el.classList].find(c => c.startsWith('creative-'));
                    el.style.display = activeGroups.includes(owningGroup) ? '' : 'none';
                });
        }

        document.getElementById('creative_type')?.addEventListener('change', updateCreativeTypeFields);
        updateCreativeTypeFields();

        // Helper: update CTA dropdown
        function updateCallToAction() {
            const creativeType = document.getElementById('creative_type')?.value;
            const ctaSelect = document.getElementById('call_to_action');
            if (!ctaSelect) return;

            ctaSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '-- Select Call to Action --';
            ctaSelect.appendChild(placeholder);

            let allowedCtAs = creativeTypeCtaMap[creativeType] || [];
            // Always include LEARN_MORE as a fallback
            if (!allowedCtAs.includes('LEARN_MORE')) {
                allowedCtAs.unshift('LEARN_MORE');
            }
            allowedCtAs.sort();

            allowedCtAs.forEach(cta => {
                const option = document.createElement('option');
                option.value = cta;
                option.textContent = beautifyLabel(cta);
                ctaSelect.appendChild(option);
            });

            ctaSelect.disabled = false;
            const currentCta = ctaSelect.dataset.previousValue || '';
            if (currentCta && allowedCtAs.includes(currentCta)) {
                ctaSelect.value = currentCta;
            } else {
                ctaSelect.value = '';
            }
            ctaSelect.dataset.previousValue = ctaSelect.value;
            ctaSelect.dispatchEvent(new Event('change'));
        }

        // Event listener for creative type change
        const creativeTypeSelect = document.getElementById('creative_type');
        if (creativeTypeSelect) {
            creativeTypeSelect.addEventListener('change', updateCallToAction);
        }

        // Initialize CTA options
        updateCallToAction();
        
        // Initialize Select2 for countries (if available)
        if ($.fn.select2) {
            $('#countries').select2();
        }

        // ------------------------------------------------------------------
        // 1. SNAPCHAT OBJECTIVE V2 MATRIX
        // Verified against developers.snap.com/marketing-api/Ads-API/campaigns
        // and .../ad-squads and .../ad-squad-ui-render-data:
        // - objective_v2_type: AWARENESS_AND_ENGAGEMENT, SALES, TRAFFIC,
        //   APP_PROMOTION, LEADS
        // - promotion_type (optional, only meaningful for
        //   AWARENESS_AND_ENGAGEMENT/APP_PROMOTION): PROMOTE_PLACES,
        //   PROMOTE_SHOWS, APP_INSTALL, APP_REENGAGEMENT
        // - conversion_location: APP, CALL, LEAD_FORM, PUBLIC_PROFILE, TEXT, WEB
        // - optimization_goal (the full real set - anything outside this list
        //   is not a real Snapchat value, however plausible it looks):
        //   IMPRESSIONS, SWIPES, APP_INSTALLS, VIDEO_VIEWS, VIDEO_VIEWS_15_SEC,
        //   USES, STORY_OPENS, PIXEL_PAGE_VIEW, PIXEL_ADD_TO_CART,
        //   LANDING_PAGE_VIEW, LEAD_FORM_SUBMISSIONS, PIXEL_PURCHASE,
        //   PIXEL_SIGNUP, APP_ADD_TO_CART, APP_PURCHASE, APP_SIGNUP,
        //   APP_REENGAGE_OPEN, APP_REENGAGE_PURCHASE
        // 'NONE' keys below mean "this level doesn't apply / has no
        // meaningful choice" - the population code renders those as an
        // empty option value rather than the literal string "NONE", since
        // Snapchat's API would reject "NONE" for either field.
        // ------------------------------------------------------------------
        const objectiveConfigV2 = {
            'AWARENESS_AND_ENGAGEMENT': {
                label: 'Awareness & Engagement',
                promotionTypes: {
                    'NONE': {
                        label: 'General Awareness',
                        conversionLocations: {
                            'NONE': {
                                label: 'Default',
                                optimizationGoals: ['IMPRESSIONS', 'SWIPES', 'STORY_OPENS', 'USES', 'VIDEO_VIEWS',
                                    'VIDEO_VIEWS_15_SEC'
                                ]
                            }
                        }
                    },
                    'PROMOTE_SHOWS': {
                        label: 'Promote Shows',
                        conversionLocations: {
                            'NONE': {
                                label: 'Default',
                                optimizationGoals: ['IMPRESSIONS']
                            }
                        }
                    },
                    'PROMOTE_PLACES': {
                        label: 'Promote Places',
                        conversionLocations: {
                            'NONE': {
                                label: 'Default',
                                optimizationGoals: ['SWIPES']
                            }
                        }
                    }
                },
                bidStrategies: ['AUTO_BID', 'LOWEST_COST_WITH_MAX_BID', 'TARGET_COST'],
                showPixel: false,
                showAppFields: false
            },
            'APP_PROMOTION': {
                label: 'App Promotion',
                promotionTypes: {
                    'APP_INSTALL': {
                        label: 'App Install',
                        conversionLocations: {
                            'APP': {
                                label: 'Mobile App',
                                optimizationGoals: ['IMPRESSIONS', 'SWIPES', 'APP_INSTALLS', 'APP_PURCHASE',
                                    'APP_SIGNUP', 'APP_ADD_TO_CART'
                                ]
                            }
                        }
                    },
                    'APP_REENGAGEMENT': {
                        label: 'App Re-engagement',
                        conversionLocations: {
                            'APP': {
                                label: 'Mobile App',
                                optimizationGoals: ['SWIPES', 'APP_REENGAGE_PURCHASE', 'APP_REENGAGE_OPEN',
                                    'IMPRESSIONS'
                                ]
                            }
                        }
                    }
                },
                bidStrategies: ['AUTO_BID', 'LOWEST_COST_WITH_MAX_BID', 'TARGET_COST'],
                showPixel: false,
                showAppFields: true
            },
            'SALES': {
                label: 'Sales',
                promotionTypes: {
                    'NONE': {
                        label: 'Standard Sales / Dynamic Ads',
                        conversionLocations: {
                            'WEB': {
                                label: 'Website',
                                optimizationGoals: ['SWIPES', 'STORY_OPENS', 'PIXEL_PURCHASE', 'PIXEL_SIGNUP',
                                    'PIXEL_ADD_TO_CART', 'PIXEL_PAGE_VIEW', 'LANDING_PAGE_VIEW'
                                ]
                            },
                            'APP': {
                                label: 'Mobile App',
                                optimizationGoals: ['IMPRESSIONS', 'SWIPES', 'APP_REENGAGE_PURCHASE',
                                    'APP_REENGAGE_OPEN', 'STORY_OPENS'
                                ]
                            }
                        }
                    }
                },
                bidStrategies: ['AUTO_BID', 'TARGET_COST', 'LOWEST_COST_WITH_MAX_BID'],
                showPixel: true,
                showAppFields: true
            },
            'LEADS': {
                label: 'Lead Generation',
                promotionTypes: {
                    'NONE': {
                        label: 'Lead Capture',
                        conversionLocations: {
                            'WEB': {
                                label: 'Website',
                                optimizationGoals: ['SWIPES', 'STORY_OPENS', 'PIXEL_SIGNUP', 'LANDING_PAGE_VIEW']
                            },
                            'LEAD_FORM': {
                                label: 'Instant Lead Form',
                                optimizationGoals: ['SWIPES', 'LEAD_FORM_SUBMISSIONS']
                            },
                            'CALL': {
                                label: 'Phone Call',
                                optimizationGoals: ['IMPRESSIONS', 'SWIPES']
                            },
                            'TEXT': {
                                label: 'Text Message',
                                optimizationGoals: ['IMPRESSIONS', 'SWIPES']
                            }
                        }
                    }
                },
                bidStrategies: ['AUTO_BID', 'LOWEST_COST_WITH_MAX_BID', 'TARGET_COST'],
                showPixel: false,
                showAppFields: false
            },
            'TRAFFIC': {
                label: 'Traffic',
                promotionTypes: {
                    'NONE': {
                        label: 'Drive Traffic',
                        conversionLocations: {
                            'WEB': {
                                label: 'Website',
                                optimizationGoals: ['SWIPES', 'PIXEL_PAGE_VIEW', 'LANDING_PAGE_VIEW']
                            },
                            'APP': {
                                label: 'Mobile App',
                                optimizationGoals: ['SWIPES', 'APP_REENGAGE_PURCHASE', 'APP_REENGAGE_OPEN',
                                    'LANDING_PAGE_VIEW'
                                ]
                            },
                            'PUBLIC_PROFILE': {
                                label: 'Public Profile',
                                optimizationGoals: ['IMPRESSIONS', 'SWIPES']
                            },
                            'CALL': {
                                label: 'Phone Call',
                                optimizationGoals: ['IMPRESSIONS', 'SWIPES']
                            },
                            'TEXT': {
                                label: 'Text Message',
                                optimizationGoals: ['IMPRESSIONS', 'SWIPES']
                            }
                        }
                    }
                },
                bidStrategies: ['AUTO_BID', 'LOWEST_COST_WITH_MAX_BID', 'TARGET_COST'],
                showPixel: false,
                showAppFields: false
            }
        };

        // There is no documented rule restricting which optimization_goal
        // values are valid per bid_strategy - the previous
        // "bidStrategyGoalRestrictions" table here wasn't sourced from
        // anything in Snap's docs and could silently hide otherwise-valid
        // goals from the dropdown, so it's gone. The only real, documented
        // per-bid-strategy fact is that MIN_ROAS was deprecated Feb 2025 and
        // is no longer offered as a bid_strategy option at all (see
        // bidStrategies arrays above).

        // Snapchat bills every ad squad by IMPRESSION regardless of which
        // optimization_goal is selected ("No matter what Optimization Goal
        // is selected, the Billing Event will still be IMPRESSION") - so
        // this isn't actually a per-goal lookup, just the one fixed value.
        const BILLING_EVENT = 'IMPRESSION';

        const bidStrategyLabels = {
            'AUTO_BID': 'Auto Bid (Lowest Cost)',
            'LOWEST_COST_WITH_MAX_BID': 'Max Bid (Cost Cap)',
            'TARGET_COST': 'Target Cost'
        };

        function beautifyLabel(value) {
            return value.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        // ------------------------------------------------------------------
        // 2. DOM REFERENCES
        // ------------------------------------------------------------------
        const objectiveSelect = document.getElementById('objective');
        const promotionTypeSelect = document.getElementById('promotion_type');
        const conversionLocationSelect = document.getElementById('conversion_location');
        const optGoalSelect = document.getElementById('optimization_goal');
        const bidStrategySelect = document.getElementById('bid_strategy');
        const billingEventSelect = document.getElementById('billing_event');

        // Helper: reset dropdown
        function resetDropdown(element, placeholderText) {
            if (!element) return;
            element.innerHTML = `<option value="">${placeholderText}</option>`;
            element.disabled = true;
            element.value = '';
        }

        // Helper: populate Optimization Goals based on Objective/Promotion
        // Type/Conversion Location - per Snap's docs, conversion_location "in
        // conjunction with the Campaign Objective V2... determines which
        // optimization goals are available", with no documented role for
        // bid_strategy in that filtering, so it isn't a further-narrowing
        // input here.
        function populateOptimizationGoals() {
            const objKey = objectiveSelect.value;
            const promoKey = promotionTypeSelect.value;
            const locKey = conversionLocationSelect.value;

            if (!objKey || !promoKey || !locKey || !objectiveConfigV2[objKey]?.promotionTypes[promoKey]?.conversionLocations[locKey]) {
                resetDropdown(optGoalSelect, '-- Select Location First --');
                resetDropdown(billingEventSelect, '-- Select Optimization Goal First --');
                return;
            }

            // Get valid goals for Objective/Promotion/Location
            let baseGoals = objectiveConfigV2[objKey].promotionTypes[promoKey].conversionLocations[locKey].optimizationGoals;

            if (baseGoals.length === 0) {
                optGoalSelect.innerHTML = `<option value="">-- No Goals Available For Selected Strategy --</option>`;
                optGoalSelect.disabled = true;
                resetDropdown(billingEventSelect, '-- Select Optimization Goal First --');
                return;
            }

            const currentSelectedGoal = optGoalSelect.value;
            let goalOptions = baseGoals.map(g =>
                `<option value="${g}">${beautifyLabel(g)}</option>`
            ).join('');
            
            optGoalSelect.innerHTML = `<option value="">-- Select Optimization Goal --</option>${goalOptions}`;
            optGoalSelect.disabled = false;

            // Preserve value if still valid
            if (currentSelectedGoal && baseGoals.includes(currentSelectedGoal)) {
                optGoalSelect.value = currentSelectedGoal;
            } else {
                optGoalSelect.value = '';
                resetDropdown(billingEventSelect, '-- Select Optimization Goal First --');
            }
        }

        // ------------------------------------------------------------------
        // 3. CASCADE LOGIC
        // ------------------------------------------------------------------
        // Objective → Promotion Types
        if (objectiveSelect) {
            objectiveSelect.addEventListener('change', function() {
                const objKey = this.value;
                const config = objectiveConfigV2[objKey];

                if (!config) {
                    resetDropdown(promotionTypeSelect, '-- Select Objective First --');
                    resetDropdown(conversionLocationSelect, '-- Select Promotion Type First --');
                    resetDropdown(optGoalSelect, '-- Select Location First --');
                    resetDropdown(bidStrategySelect, '-- Select Objective First --');
                    resetDropdown(billingEventSelect, '-- Select Optimization Goal First --');
                    return;
                }

                // Populate Promotion Types
                const promoKeys = Object.keys(config.promotionTypes);
                let promoOptions = promoKeys.map(pKey =>
                    `<option value="${pKey}">${config.promotionTypes[pKey].label}</option>`
                ).join('');
                promotionTypeSelect.innerHTML = `<option value="">-- Select Promotion Type --</option>${promoOptions}`;
                promotionTypeSelect.disabled = false;

                // Populate Bid Strategies
                if (bidStrategySelect) {
                    const bidOptions = config.bidStrategies.map(b =>
                        `<option value="${b}">${bidStrategyLabels[b] || beautifyLabel(b)}</option>`
                    ).join('');
                    bidStrategySelect.innerHTML = `<option value="">-- Select Bid Strategy --</option>${bidOptions}`;
                    bidStrategySelect.disabled = false;
                }

                // Reset downstream
                resetDropdown(conversionLocationSelect, '-- Select Promotion Type First --');
                resetDropdown(optGoalSelect, '-- Select Location First --');
                resetDropdown(billingEventSelect, '-- Select Optimization Goal First --');

                // Toggle optional fields (app/pixel)
                document.querySelectorAll('.objective-app').forEach(el => el.style.display = config.showAppFields ? '' : 'none');
                document.querySelectorAll('.objective-web').forEach(el => el.style.display = config.showPixel ? '' : 'none');
            });
        }

        // Promotion Type → Conversion Locations
        if (promotionTypeSelect) {
            promotionTypeSelect.addEventListener('change', function() {
                const objKey = objectiveSelect.value;
                const promoKey = this.value;
                if (!objKey || !promoKey || !objectiveConfigV2[objKey]?.promotionTypes[promoKey]) {
                    resetDropdown(conversionLocationSelect, '-- Select Promotion Type First --');
                    resetDropdown(optGoalSelect, '-- Select Location First --');
                    return;
                }
                const locations = objectiveConfigV2[objKey].promotionTypes[promoKey].conversionLocations;
                const locKeys = Object.keys(locations);
                let locOptions = locKeys.map(lKey =>
                    `<option value="${lKey}">${locations[lKey].label}</option>`
                ).join('');
                conversionLocationSelect.innerHTML =
                    `<option value="">-- Select Conversion Location --</option>${locOptions}`;
                conversionLocationSelect.disabled = false;
                resetDropdown(optGoalSelect, '-- Select Location First --');
                resetDropdown(billingEventSelect, '-- Select Optimization Goal First --');
            });
        }

        // Conversion Location → Optimization Goals
        if (conversionLocationSelect) {
            conversionLocationSelect.addEventListener('change', populateOptimizationGoals);
        }

        // Optimization Goal → Billing Event. Snapchat only ever bills by
        // IMPRESSION, so this just reflects that fixed value once a goal has
        // been chosen rather than looking anything up per-goal.
        if (optGoalSelect) {
            optGoalSelect.addEventListener('change', function() {
                if (this.value) {
                    billingEventSelect.innerHTML = `<option value="${BILLING_EVENT}">${BILLING_EVENT}</option>`;
                    billingEventSelect.value = BILLING_EVENT;
                    billingEventSelect.disabled = false;
                } else {
                    resetDropdown(billingEventSelect, '-- Select Optimization Goal First --');
                }
            });
        }

        // ------------------------------------------------------------------
        // 4. INITIALIZATION – trigger objective change to populate everything
        // ------------------------------------------------------------------
        if (objectiveSelect) {
            objectiveSelect.dispatchEvent(new Event('change'));
        }

        // ------------------------------------------------------------------
        // 5. BUDGET CALCULATOR
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
                if (days > 0) allocatedBudget = budget * days;
                else allocatedBudget = 0;
            }

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

        // ------------------------------------------------------------------
        // 6. REAL-TIME AD PREVIEW UPDATES
        // ------------------------------------------------------------------
        document.getElementById('name')?.addEventListener('keyup', function() {
            if (document.getElementById('previewTitle')) document.getElementById('previewTitle').innerText = this.value || 'Campaign Name';
        });

        document.getElementById('ad_description')?.addEventListener('keyup', function() {
            if (document.getElementById('previewDescription')) document.getElementById('previewDescription').innerText = this.value || 'Ad description';
        });

        // Preview CTA and URL
        const callToActionSelect = document.getElementById('call_to_action');
        const targetLinkInput = document.getElementById('target_link');
        const previewCTA = document.getElementById('previewCTA');

        if (callToActionSelect) {
            callToActionSelect.addEventListener('change', function() {
                let text = this.options[this.selectedIndex]?.text || 'Learn More';
                if (previewCTA) previewCTA.innerText = text;
            });
        }
        if (targetLinkInput) {
            targetLinkInput.addEventListener('input', function() {
                const url = this.value.trim();
                if (previewCTA) previewCTA.href = url || '#';
            });
        }

        // ------------------------------------------------------------------
        // 7. MEDIA UPLOAD & CAROUSEL
        // ------------------------------------------------------------------
        let creativeType = 'IMAGE';
        let carouselItems = [];
        let currentIndex = 0;
        const mediaInput = document.getElementById('mediaInput');
        const carouselDiv = document.getElementById('carouselPreview');

        document.querySelectorAll('.media-type').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.media-type').forEach(x => x.classList.remove('active'));
                this.classList.add('active');
                creativeType = this.dataset.type;
                if (document.getElementById('media_type')) document.getElementById('media_type').value = creativeType;

                if (mediaInput) {
                    if (creativeType === 'CAROUSEL') {
                        mediaInput.setAttribute('multiple', true);
                        mediaInput.accept = "image/*";
                        carouselItems = [];
                        currentIndex = 0;
                        document.getElementById('uploadHint').innerText = 'Upload 2+ images for the carousel (max 30MB each).';
                    } else if (creativeType === 'IMAGE') {
                        mediaInput.removeAttribute('multiple');
                        mediaInput.accept = "image/*";
                        document.getElementById('uploadHint').innerText = 'Upload image (max 30MB).';
                    } else {
                        mediaInput.removeAttribute('multiple');
                        mediaInput.accept = "video/*";
                        document.getElementById('uploadHint').innerText = 'Upload video (max 500MB). Snapchat generates the thumbnail automatically.';
                    }
                }
                // Hide previews
                if (document.getElementById('previewImage')) document.getElementById('previewImage').style.display = 'none';
                if (document.getElementById('previewVideo')) document.getElementById('previewVideo').style.display = 'none';
                if (carouselDiv) carouselDiv.style.display = 'none';
            });
        });

        function loadCarouselItem() {
            if (!carouselItems.length) return;
            let item = carouselItems[currentIndex];
            if (document.getElementById('carouselImage')) document.getElementById('carouselImage').src = item.image;
            if (document.getElementById('carouselTitle')) document.getElementById('carouselTitle').value = item.title || '';
            if (document.getElementById('carouselDescription')) document.getElementById('carouselDescription').value = item.description || '';
            if (document.getElementById('carouselCounter')) document.getElementById('carouselCounter').innerHTML = `${currentIndex + 1} / ${carouselItems.length}`;
        }

        // These were previously missing entirely, so anything typed into the
        // carousel preview's title/description never made it back into
        // carouselItems (and therefore never reached the server).
        document.getElementById('carouselTitle')?.addEventListener('input', function() {
            if (carouselItems[currentIndex]) carouselItems[currentIndex].title = this.value;
        });
        document.getElementById('carouselDescription')?.addEventListener('input', function() {
            if (carouselItems[currentIndex]) carouselItems[currentIndex].description = this.value;
        });

        if (mediaInput) {
            mediaInput.addEventListener('change', function(e) {
                let files = Array.from(e.target.files);
                let image = document.getElementById('previewImage');
                let video = document.getElementById('previewVideo');

                if (image) image.style.display = 'none';
                if (video) video.style.display = 'none';
                if (carouselDiv) carouselDiv.style.display = 'none';

                if (creativeType === 'CAROUSEL') {
                    carouselItems = files.map(file => ({
                        image: URL.createObjectURL(file),
                        title: '',
                        description: ''
                    }));
                    currentIndex = 0;
                    loadCarouselItem();
                    if (carouselDiv) carouselDiv.style.display = 'block';
                } else {
                    let file = files[0];
                    if (!file) return;
                    let url = URL.createObjectURL(file);
                    if (file.type.startsWith('image/')) {
                        if (image) {
                            image.src = url;
                            image.style.display = 'block';
                        }
                    } else {
                        if (video) {
                            video.src = url;
                            video.style.display = 'block';
                        }
                    }
                }
            });
        }

        // Carousel navigation
        document.getElementById('prevImage')?.addEventListener('click', function() {
            if (carouselItems.length) {
                currentIndex = (currentIndex - 1 + carouselItems.length) % carouselItems.length;
                loadCarouselItem();
            }
        });
        document.getElementById('nextImage')?.addEventListener('click', function() {
            if (carouselItems.length) {
                currentIndex = (currentIndex + 1) % carouselItems.length;
                loadCarouselItem();
            }
        });

        // ------------------------------------------------------------------
        // 8. WIZARD STEP NAVIGATION - Campaign / Budget / Goal / Creative /
        // Audience / Review. Mirrors the Facebook/TikTok campaign builders:
        // each section lives in a .wizard-step[data-step], only one is
        // visible at a time, and Next/Previous/the step pills all drive the
        // same showStep() so everything stays in sync.
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

        // Required fields (eg. deep_link_uri, phone_number_id) can live on a
        // step that's display:none once you've moved past it, and a hidden
        // required field can't be natively focused - so browsers silently
        // swallow the submit. Validate a step's own fields before letting
        // navigation leave it, while it's still visible and
        // reportValidity() can show the bubble.
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
                .map(el => el.nextElementSibling.innerText.trim()).join(', ');

            let mediaSummary = creativeType === 'CAROUSEL' ?
                `${carouselItems.length} carousel image(s)` :
                (mediaInput.files.length ? '1 file selected' : 'No media selected');

            let html = '';
            html += reviewRow('Campaign Name', document.getElementById('name').value);
            html += reviewRow('Objective', beautifyLabel(objectiveSelect.value || ''));
            html += reviewRow('Promotion Type', beautifyLabel(promotionTypeSelect.value || ''));
            html += reviewRow('Conversion Location', beautifyLabel(conversionLocationSelect.value || ''));
            html += reviewRow('Budget Mode', beautifyLabel(document.getElementById('budget_mode').value || ''));
            html += reviewRow('Total Budget', document.getElementById('total_budget').innerText);
            html += reviewRow('Start Date', document.getElementById('start_time').value);
            html += reviewRow('End Date', document.getElementById('end_time').value);
            html += reviewRow('Bid Strategy', beautifyLabel(bidStrategySelect.value || ''));
            html += reviewRow('Optimization Goal', beautifyLabel(optGoalSelect.value || ''));
            html += reviewRow('Creative Type', beautifyLabel(document.getElementById('creative_type').value || ''));
            html += reviewRow('Call To Action', beautifyLabel(callToActionSelect.value || ''));
            html += reviewRow('Media Type', beautifyLabel(creativeType));
            html += reviewRow('Media', mediaSummary);
            html += reviewRow('Countries', countries);
            html += reviewRow('Age Range', ageRanges);
            html += reviewRow('Gender', beautifyLabel(document.getElementById('gender').value || ''));
            html += reviewRow('Languages', languages);

            document.getElementById('reviewSummary').innerHTML = html;
        }

        // Carousel per-card headline/description are kept client-side only
        // and mirrored server-side into ad_media.title/description for our
        // own records - Snapchat's own API has no per-card fields (see the
        // note above the media upload zone). Runs on #campaign itself so it
        // fires before api.js's document-delegated submit handler builds
        // the FormData.
        document.getElementById('campaign').addEventListener('submit', function() {
            if (creativeType === 'CAROUSEL') {
                let cards = carouselItems.map(item => ({
                    title: item.title || '',
                    description: item.description || '',
                }));

                document.getElementById('carousel_cards').value = JSON.stringify(cards);
            }

            // 'NONE' is a UI-only placeholder key (objectiveConfigV2) for
            // "this objective has no meaningful promotion_type/
            // conversion_location" - it keeps the objective->promotion_type->
            // conversion_location->optimization_goal cascade working (an
            // empty option value would be indistinguishable from "nothing
            // selected yet" and break that lookup), but Snapchat's API would
            // reject the literal string "NONE" for either field, so it's
            // cleared to empty right before the form actually submits.
            ['promotion_type', 'conversion_location'].forEach(function(id) {
                let field = document.getElementById(id);
                if (field && field.value === 'NONE') {
                    field.value = '';
                }
            });
        });

        // Laravel validation errors land in .error-<field> elements
        // scattered across all six wizard steps - api.js populates them
        // then fires this event. Jump to whichever step holds the first one
        // so the message the user actually needs is visible, and flag every
        // step pill that has an error so nothing gets missed if there's
        // more than one.
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

        // Clear a field's server-side error the moment the user edits it,
        // and drop its step's error flag once nothing in that step is left
        // unresolved - so fixing a field doesn't require submitting again
        // to see progress.
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
        // 9. GLOBAL VARIABLES FOR API SUBMISSION
        // ------------------------------------------------------------------
        // These stay outside the load-deferred block above (and as plain
        // `var`, not anything scoped) since api.js - a separate script - reads
        // them as globals when #campaign is submitted.
        var url = "{{ route('admin.ads.campaigns.store', ['platform' => 'snapchat']) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'snapchat']) }}";
        var method = 'POST';
    </script>
    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
