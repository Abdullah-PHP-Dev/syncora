@extends('layouts.app')

@section('title', 'Create TikTok Campaign')

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
                            <a href="{{ route('admin.ads.campaigns.index', ['platform' => 'tiktok']) }}">
                                <button class="btn btn-primary btn-sm">
                                    <i class="bx bx-list-ul"></i> {{ __('admin.marketing_tools.ads.campaign.header') }}
                                </button>
                            </a>
                        </div>

                        <div class="campaign-builder">
                            <div class="builder-header">
                                <div class="social-icon-mini tiktok">
                                    <i class="bx bxl-tiktok"></i>
                                </div>
                                <h2>Create TikTok Campaign</h2>
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
                                                    <input type="text" name="name" id="name"
                                                        class="form-control" required>
                                                    <p class="error-message error-name"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Objective *</label>
                                                    <select id="objective" name="objective" class="form-control" required>
                                                        <option value="">-- Select Objective --</option>
                                                        <option value="APP_PROMOTION">App Promotion</option>
                                                        <option value="WEB_CONVERSIONS">Web Conversions</option>
                                                        <option value="REACH">Reach</option>
                                                        <option value="TRAFFIC">Traffic</option>
                                                        <option value="VIDEO_VIEWS">Video Views</option>
                                                        <option value="ENGAGEMENT">Engagement</option>
                                                        <option value="LEAD_GENERATION">Lead Generation</option>
                                                    </select>
                                                    <p class="error-message error-objective"></p>
                                                </div>
                                            </div>

                                            <!-- Objective-dependent fields -->
                                            <div class="row" id="objectiveDependentFields">
                                                <!-- App Promotion type (only for APP_PROMOTION) -->
                                                <div class="col-md-6 objective-app" style="display:none;">
                                                    <label>App Promotion Type</label>
                                                    <select name="app_promotion_type" id="app_promotion_type"
                                                        class="form-control">
                                                        <option value="APP_INSTALL">App Install</option>
                                                        <option value="APP_RETARGETING">App Retargeting</option>
                                                        <option value="APP_PREREGISTRATION">App Pre-registration</option>
                                                    </select>
                                                    <p class="error-message error-app_promotion_type"></p>
                                                </div>
                                                <!-- App ID (for APP_PROMOTION with APP_INSTALL or APP_RETARGETING) -->
                                                <div class="col-md-6 objective-app" style="display:none;">
                                                    <label>App ID</label>
                                                    <input type="text" name="app_id" id="app_id"
                                                        class="form-control">
                                                    <p class="error-message error-app_id"></p>
                                                </div>
                                                <!-- Promotion Website Type (for APP_PREREGISTRATION) -->
                                                <div class="col-md-6 objective-app" style="display:none;">
                                                    <label>Promotion Website Type</label>
                                                    <select name="promotion_website_type" id="promotion_website_type"
                                                        class="form-control">
                                                        <option value="UNSET">Unset</option>
                                                        <option value="TIKTOK_NATIVE_PAGE">TikTok Native Page</option>
                                                    </select>
                                                    <p class="error-message error-promotion_website_type"></p>
                                                </div>
                                                <!-- Pixel ID (for WEB_CONVERSIONS) -->
                                                <div class="col-md-6 objective-web" style="display:none;">
                                                    <label>Pixel ID</label>
                                                    <input type="text" name="pixel_id" id="pixel_id"
                                                        class="form-control">
                                                    <p class="error-message error-pixel_id"></p>
                                                </div>
                                            </div>

                                            <!-- Optimization Event - required whenever optimization_goal is IN_APP_EVENT/VALUE, or whenever a Pixel ID is set above -->
                                            <div class="row" id="optimizationEventField" style="display:none;">
                                                <div class="col-md-6">
                                                    <label>Optimization Event</label>
                                                    <input type="text" name="optimization_event" id="optimization_event"
                                                        class="form-control" placeholder="e.g. COMPLETE_PAYMENT">
                                                    <p class="error-message error-optimization_event"></p>
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
                                                <div class="col-md-4">
                                                    <label>Budget Mode *</label>
                                                    <select name="budget_mode" id="budget_mode" class="form-control"
                                                        required>
                                                        <option value="BUDGET_MODE_DAY">Daily Budget</option>
                                                        <option value="BUDGET_MODE_TOTAL">Lifetime Budget</option>
                                                    </select>
                                                    <p class="error-message error-budget_mode"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Budget *</label>
                                                    <div class="input-group">
                                                        <span
                                                            class="input-group-text">{{ $account->currency ?? 'USD' }}</span>
                                                        <input class="form-control" name="budget" id="budget"
                                                            type="number" step="0.01" min="1" required>
                                                    </div>
                                                    <p class="error-message error-budget"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Bid Amount</label>
                                                    <div class="input-group">
                                                        <span
                                                            class="input-group-text">{{ $account->currency ?? 'USD' }}</span>
                                                        <input class="form-control" name="bid_amount" id="bid_amount"
                                                            type="number" step="0.01">
                                                    </div>
                                                    <p class="error-message error-bid_amount"></p>
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
                                                                <strong>{{ $account->currency ?? 'USD' }} <span
                                                                        id="budget_amount">0.00</span></strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2 text-muted">
                                                                <span>VAT (15%)</span>
                                                                <strong>{{ $account->currency ?? 'USD' }} <span
                                                                        id="vat_amount">0.00</span></strong>
                                                            </div>
                                                            <hr>
                                                            <input type="hidden" name="final_budget" id="final_budget"
                                                                value="">
                                                            <div class="d-flex justify-content-between">
                                                                <h5 class="mb-0">Total Budget</h5>
                                                                <h5 class="mb-0 text-primary">
                                                                    {{ $account->currency ?? 'USD' }} <span
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
                                                <!-- Optimization Goal -->
                                                <div class="col-md-6">
                                                    <label>Optimization Goal *</label>
                                                    <select name="optimization_goal" id="optimization_goal"
                                                        class="form-control" required>
                                                        <option value="">-- Select Optimization Goal --</option>
                                                    </select>
                                                    <p class="error-message error-optimization_goal"></p>
                                                </div>
                                                <!-- Billing Event -->
                                                <div class="col-md-6">
                                                    <label>Billing Event *</label>
                                                    <select name="billing_event" id="billing_event" class="form-control"
                                                        required>
                                                        <option value="">-- Select Billing Event --</option>
                                                    </select>
                                                    <p class="error-message error-billing_event"></p>
                                                </div>
                                            </div>

                                            <div class="row mt-3">
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
                                                <!-- Promotion Target Type (for LEAD_GENERATION) -->
                                                <div class="col-md-6 promotion-target-block" style="display:none;">
                                                    <label>Promotion Target Type</label>
                                                    <select name="promotion_target_type" id="promotion_target_type"
                                                        class="form-control">
                                                        <option value="INSTANT_PAGE">Instant Page</option>
                                                        <option value="EXTERNAL_WEBSITE">External Website</option>
                                                    </select>
                                                    <p class="error-message error-promotion_target_type"></p>
                                                </div>
                                            </div>

                                            <div class="row mt-3">
                                                <!-- Destination Type (optional for some) -->
                                                {{-- <div class="col-md-6">
                                                    <label>Destination Type</label>
                                                    <select name="destination_type" id="destination_type"
                                                        class="form-control">
                                                        <option value="">-- Select Destination Type --</option>
                                                    </select>
                                                    <p class="error-message error-destination_type"></p>
                                                </div> --}}
                                                <!-- TikTok requires a linked TikTok account Identity on every ad - Custom Identity was deprecated platform-wide in TikTok's January 2026 F.I.R.S.T. policy rollout, so this can only list real linked accounts, not create one -->
                                                <div class="col-md-6">
                                                    <label>TikTok Identity *</label>
                                                    <select name="page_id" id="page_id" class="form-control" required>
                                                        <option value="">-- Loading identities... --</option>
                                                    </select>
                                                    <input type="hidden" name="identity_type" id="identity_type" value="TT_USER">
                                                    <small class="text-muted">A TikTok account linked to this ad account. If none are listed, link one in TikTok Ads Manager → Assets → Identity (or via Business Center) first - this can't be created from here.</small>
                                                    <p class="error-message error-page_id"></p>
                                                </div>
                                            </div>

                                            <!-- Additional dynamic fields for messaging apps etc. -->
                                            <div class="row mt-3" id="dynamicGoalFields">
                                                <!-- Messaging App Type -->
                                                <div class="col-md-6 messaging-app-block" style="display:none;">
                                                    <label>Messaging App Type</label>
                                                    <select name="messaging_app_type" id="messaging_app_type"
                                                        class="form-control">
                                                        <option value="">-- Select --</option>
                                                        <option value="MESSENGER">Messenger</option>
                                                        <option value="WHATSAPP">WhatsApp</option>
                                                        <option value="ZALO">Zalo</option>
                                                        <option value="LINE">Line</option>
                                                        <option value="IM_URL">Instant Messaging URL</option>
                                                    </select>
                                                    <p class="error-message error-messaging_app_type"></p>
                                                </div>
                                                <!-- Messaging App Account ID -->
                                                <div class="col-md-6 messaging-account-block" style="display:none;">
                                                    <label>Messaging App Account ID</label>
                                                    <input type="text" name="messaging_app_account_id"
                                                        id="messaging_app_account_id" class="form-control">
                                                    <p class="error-message error-messaging_app_account_id"></p>
                                                </div>
                                                <!-- Phone region code (for WhatsApp/Zalo) -->
                                                <div class="col-md-6 phone-block" style="display:none;">
                                                    <label>Phone Region Code</label>
                                                    <input type="text" name="phone_region_code" id="phone_region_code"
                                                        class="form-control">
                                                    <p class="error-message error-phone_region_code"></p>
                                                </div>
                                                <!-- Phone number -->
                                                <div class="col-md-6 phone-block" style="display:none;">
                                                    <label>Phone Number</label>
                                                    <input type="text" name="phone_number" id="phone_number"
                                                        class="form-control">
                                                    <p class="error-message error-phone_number"></p>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="4">
                                        <!-- ============================================================ -->
                                        <!-- 4. AD CREATIVE (Ad level)                                   -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Ad Creative</h5>
                                            <div class="duration-buttons">
                                                <input type="hidden" name="media_type" id="media_type" value="IMAGE">
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
                                                <p id="uploadHint">Upload image (max 30MB) or video (max 500MB).</p>
                                                <input type="file" name="media[]" id="mediaInput" hidden
                                                    accept="image/*,video/*">
                                                <button type="button" class="btn btn-primary"
                                                    onclick="document.getElementById('mediaInput').click()">Upload
                                                    Media</button>
                                                <p class="error-message error-media"></p>
                                            </div>
                                            <div class="mt-3" id="carouselMusicBlock" style="display:none;">
                                                <label>Background Music *</label>
                                                <input type="file" name="music" id="musicInput" class="form-control" accept=".mp3,.wav,.m4a,.flac">
                                                <small class="text-muted">Required for Carousel Ads - TikTok won't create the ad without one. MP3/WAV/M4A/FLAC, max 10MB.</small>
                                                <p class="error-message error-music"></p>
                                            </div>
                                            <div class="mt-3" id="videoCoverBlock" style="display:none;">
                                                <label>Video Cover / Thumbnail</label>
                                                <input type="file" name="video_cover" id="videoCoverInput" class="form-control" accept="image/*">
                                                <small class="text-muted">Optional - if you don't upload one, TikTok will auto-generate a cover from a frame of your video.</small>
                                                <p class="error-message error-video_cover"></p>
                                            </div>
                                            <div class="mt-4">
                                                <label>Description</label>
                                                <textarea id="ad_description" name="description" rows="4" class="form-control"></textarea>
                                                <p class="error-message error-description"></p>
                                            </div>
                                        </div>
                                        <div class="builder-card">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Target URL (Landing Page)</label>
                                                    <input type="url" name="target_link" id="target_link"
                                                        class="form-control" placeholder="https://example.com">
                                                    <small class="text-muted" id="carouselLinkNote" style="display:none">TikTok Carousel Ads use one landing page URL, caption and call-to-action shared across every card - not a URL per image.</small>
                                                    <p class="error-message error-target_link"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Call to Action</label>
                                                    <select name="call_to_action" id="call_to_action" class="form-control">
                                                        <option value="">-- Select CTA --</option>
                                                        <option value="APPLY_NOW">Apply Now</option>
                                                        <option value="BOOK_NOW">Book Now</option>
                                                        <option value="CALL_NOW">Call Now</option>
                                                        <option value="CHECK_AVAILABLILITY">Check Availability</option>
                                                        <option value="CONTACT_US">Contact Us</option>
                                                        <option value="DOWNLOAD_NOW">Download Now</option>
                                                        <option value="EXPERIENCE_NOW">Experience Now</option>
                                                        <option value="GET_QUOTE">Get Quote</option>
                                                        <option value="GET_SHOWTIMES">Get Showtimes</option>
                                                        <option value="GET_TICKETS_NOW">Get Tickets Now</option>
                                                        <option value="INSTALL_NOW">Install Now</option>
                                                        <option value="INTERESTED">Interested</option>
                                                        <option value="LEARN_MORE">Learn More</option>
                                                        <option value="LISTEN_NOW">Listen Now</option>
                                                        <option value="ORDER_NOW">Order Now</option>
                                                        <option value="PLAY_GAME">Play Game</option>
                                                        <option value="PREORDER_NOW">Preorder Now</option>
                                                        <option value="READ_MORE">Read More</option>
                                                        <option value="SEND_MESSAGE">Send Message</option>
                                                        <option value="SHOP_NOW">Shop Now</option>
                                                        <option value="SIGN_UP">Sign Up</option>
                                                        <option value="SUBSCRIBE">Subscribe</option>
                                                        <option value="VIEW_NOW">View Now</option>
                                                        <option value="VIEW_PROFILE">View Profile</option>
                                                        <option value="VISIT_STORE">Visit Store</option>
                                                        <option value="WATCH_LIVE">Watch Live</option>
                                                        <option value="WATCH_NOW">Watch Now</option>
                                                    </select>
                                                    <p class="error-message error-call_to_action"></p>
                                                </div>
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
                                                        <option value="GENDER_UNLIMITED">All</option>
                                                        <option value="GENDER_MALE">Male</option>
                                                        <option value="GENDER_FEMALE">Female</option>
                                                    </select>
                                                    <p class="error-message error-gender"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Age Range</label>
                                                    <div class="checkbox-group">
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_18_24" id="age_18_24">
                                                            <label class="form-check-label" for="age_18_24">18 – 24</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_25_34" id="age_25_34">
                                                            <label class="form-check-label" for="age_25_34">25 – 34</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_35_44" id="age_35_44">
                                                            <label class="form-check-label" for="age_35_44">35 – 44</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_45_54" id="age_45_54">
                                                            <label class="form-check-label" for="age_45_54">45 – 54</label>
                                                        </div>
                                                        <div class="form-check form-switch">
                                                            <input class=" form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_55_100" id="age_55_100">
                                                            <label class="form-check-label" for="age_55_100">55+</label>
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
                                            <div class="row mt-3">
                                                <div class="col-md-4">
                                                    <label>Placement Type</label>
                                                    <select name="placement_type" id="placement_type" class="form-control">
                                                        <option value="PLACEMENT_TYPE_AUTOMATIC" selected>Automatic Placement</option>
                                                        <option value="PLACEMENT_TYPE_NORMAL">Select Placements Manually</option>
                                                    </select>
                                                    <p class="error-message error-placement_type"></p>
                                                </div>
                                                <div class="col-md-8 placements-block" style="display:none;">
                                                    <label>Placements</label>
                                                    <div class="platform-group">
                                                        @foreach (['PLACEMENT_TIKTOK' => 'TikTok', 'PLACEMENT_PANGLE' => 'Pangle', 'PLACEMENT_GLOBAL_APP_BUNDLE' => 'Global App Bundle'] as $value => $label)
                                                            <div class="platform-card">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input platform-switch"
                                                                        type="checkbox" name="placements[]"
                                                                        value="{{ $value }}"
                                                                        id="placement_{{ strtolower($value) }}">
                                                                    <label class="form-check-label ms-2"
                                                                        for="placement_{{ strtolower($value) }}">{{ $label }}</label>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-placements"></p>
                                                </div>
                                            </div>
                                            <hr class="my-4">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Operating System</label>
                                                    <div class="platform-group">
                                                        @foreach (['ANDROID' => 'Android', 'IOS' => 'iOS'] as $value => $label)
                                                            <div class="platform-card">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input platform-switch" type="checkbox" name="operating_systems[]" value="{{ $value }}" id="os_{{ strtolower($value) }}">
                                                                    <label class="form-check-label ms-2" for="os_{{ strtolower($value) }}">{{ $label }}</label>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-operating_systems"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Network</label>
                                                    <div class="platform-group">
                                                        @foreach (['WIFI' => 'Wi-Fi', '2G' => '2G', '3G' => '3G', '4G' => '4G/LTE'] as $value => $label)
                                                            <div class="platform-card">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input platform-switch" type="checkbox" name="network_types[]" value="{{ $value }}" id="network_{{ strtolower($value) }}">
                                                                    <label class="form-check-label ms-2" for="network_{{ strtolower($value) }}">{{ $label }}</label>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-network_types"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Device Price Range (USD, optional)</label>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <input type="number" name="device_price_min" id="device_price_min" class="form-control" min="0" placeholder="Min">
                                                            <p class="error-message error-device_price_min"></p>
                                                        </div>
                                                        <div class="col-6">
                                                            <input type="number" name="device_price_max" id="device_price_max" class="form-control" min="0" placeholder="Max">
                                                            <p class="error-message error-device_price_max"></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr class="my-4">
                                            <h6>Interests &amp; Behaviors</h6>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <label>Interest Categories (comma-separated)</label>
                                                    <input type="text" name="interest_categories" id="interest_categories" class="form-control" placeholder="e.g. Beauty & Personal Care, Gaming">
                                                    <p class="error-message error-interest_categories"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Additional Interest Keywords (comma-separated)</label>
                                                    <input type="text" name="interest_keywords" id="interest_keywords" class="form-control" placeholder="e.g. skincare, home workout">
                                                    <p class="error-message error-interest_keywords"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Behaviors (comma-separated)</label>
                                                    <input type="text" name="behaviors" id="behaviors" class="form-control" placeholder="e.g. Engaged with Fashion Videos">
                                                    <p class="error-message error-behaviors"></p>
                                                </div>
                                            </div>
                                            <hr class="my-4">
                                            <h6>Custom Audiences</h6>
                                            <p class="text-muted mb-1" style="font-size:0.8rem;">Paste in existing Custom Audience IDs from TikTok Ads Manager (comma-separated) - this app doesn't create new audiences, only targets ones you've already built.</p>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Include Audiences</label>
                                                    <input type="text" name="audience_ids" id="audience_ids" class="form-control" placeholder="e.g. 7123456789012345678">
                                                    <p class="error-message error-audience_ids"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Exclude Audiences</label>
                                                    <input type="text" name="excluded_audience_ids" id="excluded_audience_ids" class="form-control" placeholder="e.g. 7123456789012345679">
                                                    <p class="error-message error-excluded_audience_ids"></p>
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
                                                <small class="text-muted d-block mt-2">TikTok Carousel Ads share one caption and CTA across every card (set below the upload zone) - there's no per-image title or link.</small>
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
    // The shared layout mounts a Vue 2 root on #app with no template/render
    // option (resources/js/app.js: `new Vue({ el: '#app' })`), so Vue
    // compiles this form's server-rendered HTML as an in-DOM template and
    // re-renders it once its module script finishes loading - after this
    // ordinary inline script has already run and attached listeners/cached
    // references to the original nodes. That swaps every one of them out
    // from under us, so everything below is deferred to `load`, which waits
    // for that module script (and Vue's mount) to finish first.
    window.addEventListener('load', function() {
    // Initialize Select2 for countries
    $('#countries').select2();

    // ------------------------------------------------------------------
    // 1. TIKTOK API MAPPINGS (based on official docs + your table)
    // ------------------------------------------------------------------

    // Optimization Goal → Billing Event (as per your table)
    const optimizationGoalBillingMap = {
        'CLICK': 'CPC',
        'PAGE_VISIT': 'CPC',
        'CONVERT': 'OCPM',
        'INSTALL': 'OCPM',
        'IN_APP_EVENT': 'OCPM',
        'TRAFFIC_LANDING_PAGE_VIEW': 'OCPM',
        'LEAD_GENERATION': 'OCPM',
        'CONVERSATION': 'OCPM',
        'FOLLOWERS': 'OCPM',
        'VALUE': 'OCPM',
        'AUTOMATIC_VALUE_OPTIMIZATION': 'OCPM',
        'PRODUCT_CLICK_IN_LIVE': 'OCPM',
        'MT_LIVE_ROOM': 'OCPM',
        'DESTINATION_VISIT': 'OCPM',
        'SHOW': 'CPM',
        'REACH': 'CPM',
        'ENGAGED_VIEW': 'CPV',
        'ENGAGED_VIEW_FIFTEEN': 'CPV'
    };
    // Objective → allowed optimization goals & other fields
    const objectiveConfig = {
        'APP_PROMOTION': {
            optimizationGoals: ["INSTALL", "IN_APP_EVENT", "VALUE"],
            promotionTypes: ['APP_ANDROID', 'APP_IOS', 'MINI_APP', 'MINI_GAME', 'GAME'],
            destinationTypes: ['APP'],
            showPixel: false,
            showAppFields: true,
            brandSafetyType: 'NO_BRAND_SAFETY',
        },
        'WEB_CONVERSIONS': {
            optimizationGoals: ["CONVERT", "VALUE", "AUTOMATIC_VALUE_OPTIMIZATION"],
            promotionTypes: ['WEBSITE', 'WEBSITE_OR_DISPLAY'],
            destinationTypes: ['WEBSITE', 'APP'],
            showPixel: true,
            showAppFields: false,
            brandSafetyType: 'NO_BRAND_SAFETY',
        },
        'REACH': {
            optimizationGoals: ['REACH'],
            promotionTypes: ['WEBSITE', 'EXTERNAL_OR_DISPLAY'],
            destinationTypes: ['WEBSITE'],
            showPixel: false,
            showAppFields: false,
            brandSafetyType: 'STANDARD',
        },
        'TRAFFIC': {
            optimizationGoals: ["CLICK", "TRAFFIC_LANDING_PAGE_VIEW"],
            promotionTypes: ['WEBSITE', 'WEBSITE_OR_DISPLAY'],
            destinationTypes: ['WEBSITE', 'APP'],
            showPixel: false,
            showAppFields: false,
            brandSafetyType: 'NO_BRAND_SAFETY',
        },
        'VIDEO_VIEWS': {
            optimizationGoals: ["ENGAGED_VIEW", "ENGAGED_VIEW_FIFTEEN"],
            promotionTypes: ['WEBSITE', "WEBSITE_OR_DISPLAY"],
            destinationTypes: ['WEBSITE'],
            showPixel: false,
            showAppFields: false,
            brandSafetyType: 'STANDARD',
        },
        'ENGAGEMENT': {
            optimizationGoals: ["FOLLOWERS", "PAGE_VISIT"],
            promotionTypes: ['EXTERNAL_OR_DISPLAY', 'WEBSITE'],
            destinationTypes: ['MESSENGER', 'WHATSAPP'],
            showPixel: false,
            showAppFields: false,
            brandSafetyType: 'NO_BRAND_SAFETY',
        },
        'LEAD_GENERATION': {
            optimizationGoals: ['LEAD_GENERATION'],
            promotionTypes: ['LEAD_GENERATION', 'LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE', 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE', 'LEAD_GEN_CLICK_TO_CALL'],
            destinationTypes: ['WEBSITE', 'INSTANT_FORM'],
            showPixel: false,
            showAppFields: false,
            brandSafetyType: 'NO_BRAND_SAFETY',
        },
    };

    const promotionTypeLabels = {
        'APP_ANDROID': 'Android App',
        'APP_IOS': 'iOS App',
        'MINI_APP': 'Mini App',
        'MINI_GAME': 'Mini Game',
        'GAME': 'Game',
        'WEBSITE': 'Website',
        'LEAD_GENERATION': 'Lead Generation (Instant Form/Website)',
        'LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE': 'Lead via TikTok DM',
        'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE': 'Lead via Social Media App Message',
        'LEAD_GEN_CLICK_TO_CALL': 'Lead via Phone Call',
        'WEBSITE_OR_DISPLAY': 'Website or Display',
        'EXTERNAL_OR_DISPLAY': 'External or Display',
    };

    const destinationTypeLabels = {
        'WEBSITE': 'Website',
        'APP': 'App',
        'MESSENGER': 'Messenger',
        'WHATSAPP': 'WhatsApp',
        'INSTANT_FORM': 'Instant Form',
    };

    // ------------------------------------------------------------------
    // 2. DOM REFS
    // ------------------------------------------------------------------
    const qs = document.querySelector.bind(document);
    const qsa = document.querySelectorAll.bind(document);

    const objectiveSelect = qs('#objective');
    const optGoalSelect = qs('#optimization_goal');
    const billingEventSelect = qs('#billing_event');
    const promotionTypeSelect = qs('#promotion_type');
    //const destinationTypeSelect = qs('#destination_type');
    const callToActionSelect = qs('#call_to_action');
    const targetLinkInput = qs('#target_link');
    const previewCTA = document.getElementById('previewCTA');

    // ------------------------------------------------------------------
    // LOAD TIKTOK IDENTITIES - replaces a free-text identity id (which
    // could silently reference an identity TikTok has since revoked
    // access to) with only the identities currently authorized for this
    // ad account, per TiktokAdService::getIdentities(). Each option
    // carries its identity_type as a data attribute - buildCreative()
    // needs the type to match the id or TikTok rejects the request, so
    // #identity_type is kept in sync with whichever option is selected.
    // ------------------------------------------------------------------
    const identityUrl = "{{ route('admin.ads.identities', ['platform' => 'tiktok']) }}";
    const identitySelect = qs('#page_id');
    const identityTypeInput = qs('#identity_type');

    function renderIdentityOptions(identities) {
        if (!identitySelect) return;

        if (identities.length) {
            identitySelect.innerHTML = '<option value="">-- Select Identity --</option>' +
                identities.map(identity => `<option value="${identity.id}" data-type="${identity.type}">${identity.name}</option>`).join('');
        } else {
            identitySelect.innerHTML = '<option value="">-- No linked TikTok accounts - add one in TikTok Ads Manager --</option>';
        }

        if (identityTypeInput) identityTypeInput.value = identitySelect.selectedOptions[0]?.dataset.type || 'TT_USER';
    }

    function loadIdentities() {
        return fetch(identityUrl)
            .then(response => response.json())
            .then(result => {
                renderIdentityOptions(result.success ? result.data : []);
                return result;
            })
            .catch(() => {
                if (identitySelect) identitySelect.innerHTML = '<option value="">-- Could not load identities --</option>';
            });
    }

    loadIdentities();

    if (identitySelect) {
        identitySelect.addEventListener('change', function() {
            if (identityTypeInput) identityTypeInput.value = this.selectedOptions[0]?.dataset.type || 'TT_USER';
        });
    }

    // ------------------------------------------------------------------
    // 3. HELPER: beautify label
    // ------------------------------------------------------------------
    function beautifyLabel(value) {
        return value.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
    }

    // ------------------------------------------------------------------
    // 4. POPULATE DROPDOWNS BASED ON OBJECTIVE
    // ------------------------------------------------------------------
    function populateFields(objective) {
        const config = objectiveConfig[objective];
        if (!config) return;

        // Optimization Goals
        const goalOptions = config.optimizationGoals.map(g =>
            `<option value="${g}">${beautifyLabel(g)}</option>`
        ).join('');
        optGoalSelect.innerHTML = `<option value="">-- Select Optimization Goal --</option>${goalOptions}`;
        optGoalSelect.disabled = false;

        // Billing Event – will be updated on optimization_goal change, initially empty
        billingEventSelect.innerHTML = `<option value="">-- Select Billing Event --</option>`;
        billingEventSelect.disabled = true; // will be enabled when goal is selected

        // Promotion Types
        const promOptions = config.promotionTypes.map(p =>
            `<option value="${p}">${promotionTypeLabels[p] || p}</option>`
        ).join('');
        promotionTypeSelect.innerHTML = `<option value="">-- Select Promotion Type --</option>${promOptions}`;
        promotionTypeSelect.disabled = false;

        // Destination Types (if any)
        // if (config.destinationTypes && config.destinationTypes.length) {
        //     const destOptions = config.destinationTypes.map(d =>
        //         `<option value="${d}">${destinationTypeLabels[d] || d}</option>`
        //     ).join('');
        //     destinationTypeSelect.innerHTML = `<option value="">-- Select Destination Type --</option>${destOptions}`;
        //     destinationTypeSelect.disabled = false;
        // } else {
        //     destinationTypeSelect.innerHTML = `<option value="">Not applicable</option>`;
        //     destinationTypeSelect.disabled = true;
        // }

        // Show/hide objective-dependent fields
        const isApp = objective === 'APP_PROMOTION';
        document.querySelectorAll('.objective-app').forEach(el => el.style.display = isApp ? '' : 'none');
        document.querySelectorAll('.objective-web').forEach(el => el.style.display = objective === 'WEB_CONVERSIONS' ? '' : 'none');

        // Trigger change to update sub-fields
        promotionTypeSelect.dispatchEvent(new Event('change'));
        // Reset optimization goal and billing event
        optGoalSelect.value = '';
        billingEventSelect.value = '';
        billingEventSelect.disabled = true;
    }

    // ------------------------------------------------------------------
    // 5. UPDATE BILLING EVENT WHEN OPTIMIZATION GOAL CHANGES
    // ------------------------------------------------------------------
    optGoalSelect.addEventListener('change', function() {
        const goal = this.value;
        if (goal && optimizationGoalBillingMap.hasOwnProperty(goal)) {
            const billing = optimizationGoalBillingMap[goal];
            // Set billing event dropdown to the corresponding value
            billingEventSelect.innerHTML = `<option value="${billing}">${billing}</option>`;
            billingEventSelect.value = billing;
            billingEventSelect.disabled = false;
        } else {
            billingEventSelect.innerHTML = `<option value="">-- Select Billing Event --</option>`;
            billingEventSelect.disabled = true;
        }
    });

    // ------------------------------------------------------------------
    // 6. SHOW/HIDE LOGIC FOR PROMOTION TYPE
    // ------------------------------------------------------------------
    promotionTypeSelect.addEventListener('change', function() {
        const promType = this.value;

        // Promotion Target Type (only for lead gen related)
        const showPromoTarget = (promType === 'LEAD_GENERATION' || promType === 'LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE' || promType === 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE' || promType === 'LEAD_GEN_CLICK_TO_CALL');
        const targetBlock = document.querySelector('.promotion-target-block');
        if (targetBlock) targetBlock.style.display = showPromoTarget ? '' : 'none';

        // Messaging app fields
        const showMessaging = (promType === 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE');
        const msgBlock = document.querySelector('.messaging-app-block');
        if (msgBlock) msgBlock.style.display = showMessaging ? '' : 'none';
        if (!showMessaging) {
            const accBlock = document.querySelector('.messaging-account-block');
            if (accBlock) accBlock.style.display = 'none';
            document.querySelectorAll('.phone-block').forEach(el => el.style.display = 'none');
        }
    });

    // ------------------------------------------------------------------
    // SHOW/HIDE LOGIC FOR PLACEMENT TYPE - TikTok's adgroup/create/ only
    // accepts a placements[] list when placement_type is
    // PLACEMENT_TYPE_NORMAL; for AUTOMATIC it must be omitted entirely,
    // so the checkboxes (and whatever's checked in them) only matter -
    // and only get validated/sent - when Manual is selected.
    // ------------------------------------------------------------------
    const placementTypeSelect = qs('#placement_type');
    if (placementTypeSelect) {
        placementTypeSelect.addEventListener('change', function() {
            const placementsBlock = document.querySelector('.placements-block');
            if (placementsBlock) placementsBlock.style.display = this.value === 'PLACEMENT_TYPE_NORMAL' ? '' : 'none';
        });
    }

    // Messaging app type change (for account ID / phone)
    const messagingAppType = qs('#messaging_app_type');
    if (messagingAppType) {
        messagingAppType.addEventListener('change', function() {
            const val = this.value;
            const showAccount = (val === 'MESSENGER' || val === 'LINE');
            const accBlock = document.querySelector('.messaging-account-block');
            if (accBlock) accBlock.style.display = showAccount ? '' : 'none';

            const showPhone = (val === 'WHATSAPP' || val === 'ZALO');
            document.querySelectorAll('.phone-block').forEach(el => el.style.display = showPhone ? '' : 'none');
        });
    }

    // ------------------------------------------------------------------
    // 7. OBJECTIVE CHANGE
    // ------------------------------------------------------------------
    objectiveSelect.addEventListener('change', function() {
        const objective = this.value;
        if (objective && objectiveConfig[objective]) {
            populateFields(objective);
        } else {
            // Clear and disable
            optGoalSelect.innerHTML = '<option value="">-- Select Objective first --</option>';
            billingEventSelect.innerHTML = '<option value="">-- Select Objective first --</option>';
            promotionTypeSelect.innerHTML = '<option value="">-- Select Objective first --</option>';
         //   destinationTypeSelect.innerHTML = '<option value="">-- Select Objective first --</option>';
            optGoalSelect.disabled = true;
            billingEventSelect.disabled = true;
            promotionTypeSelect.disabled = true;
         //   destinationTypeSelect.disabled = true;
        }
    });

    // ------------------------------------------------------------------
    // 8. BUDGET CALCULATOR (unchanged)
    // ------------------------------------------------------------------
    function calculateBudget() {
        let budgetMode = document.getElementById('budget_mode').value;
        let budget = parseFloat(document.getElementById('budget').value) || 0;
        let startDate = document.getElementById('start_time').value;
        let endDate = document.getElementById('end_time').value;
        let allocatedBudget = budget;

        if (budgetMode === 'BUDGET_MODE_DAY' && startDate && endDate) {
            let start = new Date(startDate);
            let end = new Date(endDate);
            let days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            if (days > 0) allocatedBudget = budget * days;
            else allocatedBudget = 0;
        }

        let vat = allocatedBudget * 0.15;
        let total = allocatedBudget + vat;
        document.getElementById('budget_amount').innerText = allocatedBudget.toFixed(2);
        document.getElementById('vat_amount').innerText = vat.toFixed(2);
        document.getElementById('total_budget').innerText = total.toFixed(2);
        document.getElementById('final_budget').value = total.toFixed(2);
    }

    document.getElementById('budget').addEventListener('input', calculateBudget);
    document.getElementById('budget_mode').addEventListener('change', calculateBudget);
    document.getElementById('start_time').addEventListener('change', calculateBudget);
    document.getElementById('end_time').addEventListener('change', calculateBudget);

    // ------------------------------------------------------------------
    // 9. PREVIEW UPDATES
    // ------------------------------------------------------------------
    document.getElementById('name').addEventListener('keyup', function() {
        document.getElementById('previewTitle').innerText = this.value || 'Campaign Name';
    });

    document.getElementById('ad_description').addEventListener('keyup', function() {
        document.getElementById('previewDescription').innerText = this.value || 'Ad description';
    });

    document.getElementById('target_link').addEventListener('keyup', function() {
        document.getElementById('previewCTA').href = this.value || '#';
    });

    callToActionSelect.addEventListener('change', function() {
        let text = this.options[this.selectedIndex]?.text || 'Learn More';
        document.getElementById('previewCTA').innerText = text;
    });

    // Update CTA link
    targetLinkInput.addEventListener('input', function () {
        const url = this.value.trim();

        previewCTA.href = url || '#';
    });
    // ------------------------------------------------------------------
    // 10. MEDIA UPLOAD & CAROUSEL
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
            document.getElementById('media_type').value = creativeType;

            document.getElementById('carouselLinkNote').style.display = creativeType === 'CAROUSEL' ? '' : 'none';
            document.getElementById('carouselMusicBlock').style.display = creativeType === 'CAROUSEL' ? '' : 'none';
            document.getElementById('videoCoverBlock').style.display = creativeType === 'VIDEO' ? '' : 'none';

            if (creativeType === 'CAROUSEL') {
                mediaInput.setAttribute('multiple', true);
                mediaInput.accept = "image/*";
                carouselItems = [];
                currentIndex = 0;
                document.getElementById('uploadHint').innerText = 'Upload 2-35 images for the carousel (max 30MB each).';
            } else if (creativeType === 'IMAGE') {
                mediaInput.removeAttribute('multiple');
                mediaInput.accept = "image/*";
                document.getElementById('uploadHint').innerText = 'Upload image (max 30MB).';
            } else {
                mediaInput.removeAttribute('multiple');
                mediaInput.accept = "video/*";
                document.getElementById('uploadHint').innerText = 'Upload video (max 500MB).';
            }
        });
    });

    function loadCarouselItem() {
        if (!carouselItems.length) return;
        let item = carouselItems[currentIndex];
        document.getElementById('carouselImage').src = item.image;
        document.getElementById('carouselCounter').innerHTML = `${currentIndex + 1} / ${carouselItems.length}`;
    }

    mediaInput.addEventListener('change', function(e) {
        let files = Array.from(e.target.files);
        let image = document.getElementById('previewImage');
        let video = document.getElementById('previewVideo');

        image.style.display = 'none';
        video.style.display = 'none';
        carouselDiv.style.display = 'none';

        if (creativeType === 'CAROUSEL') {
            carouselItems = files.map(file => ({
                image: URL.createObjectURL(file),
            }));
            currentIndex = 0;
            loadCarouselItem();
            carouselDiv.style.display = 'block';
        } else {
            let file = files[0];
            if (!file) return;
            let url = URL.createObjectURL(file);
            if (file.type.startsWith('image/')) {
                image.src = url;
                image.style.display = 'block';
            } else {
                video.src = url;
                video.style.display = 'block';
            }
        }
    });

    document.getElementById('nextImage').addEventListener('click', function() {
        if (currentIndex < carouselItems.length - 1) { currentIndex++; loadCarouselItem(); }
    });
    document.getElementById('prevImage').addEventListener('click', function() {
        if (currentIndex > 0) { currentIndex--; loadCarouselItem(); }
    });

    // ------------------------------------------------------------------
    // 11. OPTIMIZATION EVENT VISIBILITY
    // Required whenever optimization_goal is IN_APP_EVENT/VALUE, or
    // whenever a Pixel ID has been entered (see AdCampaignRequest).
    // ------------------------------------------------------------------
    function updateOptimizationEventVisibility() {
        let goal = optGoalSelect.value;
        let hasPixel = document.getElementById('pixel_id').value.trim() !== '';
        let needsEvent = ['IN_APP_EVENT', 'VALUE'].includes(goal) || hasPixel;

        document.getElementById('optimizationEventField').style.display = needsEvent ? '' : 'none';
    }

    optGoalSelect.addEventListener('change', updateOptimizationEventVisibility);
    document.getElementById('pixel_id').addEventListener('input', updateOptimizationEventVisibility);

    // ------------------------------------------------------------------
    // 12. WIZARD STEP NAVIGATION - Campaign / Budget / Goal / Creative /
    // Audience / Review. Mirrors the Facebook campaign builder: each
    // section lives in a .wizard-step[data-step], only one is visible at
    // a time, and Next/Previous/the step pills all drive the same
    // showStep() so everything stays in sync.
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

    // Required fields (eg. pixel_id, optimization_event) can live on a step
    // that's display:none once you've moved past it, and a hidden required
    // field can't be natively focused - so browsers silently swallow the
    // submit. Validate a step's own fields before letting navigation leave
    // it, while it's still visible and reportValidity() can show the bubble.
    function stepIsValid(stepNumber) {
        let stepEl = document.querySelector(`.wizard-step[data-step="${stepNumber}"]`);
        let fields = stepEl.querySelectorAll('input, select, textarea');

        for (let field of fields) {
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

        let operatingSystems = Array.from(document.querySelectorAll('input[name="operating_systems[]"]:checked'))
            .map(el => el.nextElementSibling.innerText.trim()).join(', ');

        let networkTypes = Array.from(document.querySelectorAll('input[name="network_types[]"]:checked'))
            .map(el => el.nextElementSibling.innerText.trim()).join(', ');

        let mediaSummary = creativeType === 'CAROUSEL' ?
            `${carouselItems.length} carousel image(s)` :
            (mediaInput.files.length ? '1 file selected' : 'No media selected');

        let html = '';
        html += reviewRow('Campaign Name', document.getElementById('name').value);
        html += reviewRow('Objective', beautifyLabel(document.getElementById('objective').value || ''));
        html += reviewRow('Budget Mode', beautifyLabel(document.getElementById('budget_mode').value || ''));
        html += reviewRow('Total Budget', document.getElementById('total_budget').innerText);
        html += reviewRow('Start Date', document.getElementById('start_time').value);
        html += reviewRow('End Date', document.getElementById('end_time').value);
        html += reviewRow('Optimization Goal', beautifyLabel(optGoalSelect.value || ''));
        html += reviewRow('Billing Event', billingEventSelect.value);
        html += reviewRow('Promotion Type', beautifyLabel(promotionTypeSelect.value || ''));
        html += reviewRow('Media Type', beautifyLabel(creativeType));
        html += reviewRow('Media', mediaSummary);
        html += reviewRow('Call To Action', beautifyLabel(callToActionSelect.value || ''));
        html += reviewRow('Countries', countries);
        html += reviewRow('Age Range', ageRanges);
        html += reviewRow('Gender', beautifyLabel(document.getElementById('gender').value || ''));
        html += reviewRow('Languages', languages);
        html += reviewRow('Operating System', operatingSystems);
        html += reviewRow('Network', networkTypes);
        html += reviewRow('Device Price Range', (document.getElementById('device_price_min').value || '-') + ' - ' + (document.getElementById('device_price_max').value || '-'));
        html += reviewRow('Interest Categories', document.getElementById('interest_categories').value);
        html += reviewRow('Additional Interests', document.getElementById('interest_keywords').value);
        html += reviewRow('Behaviors', document.getElementById('behaviors').value);
        html += reviewRow('Include Audiences', document.getElementById('audience_ids').value);
        html += reviewRow('Exclude Audiences', document.getElementById('excluded_audience_ids').value);

        document.getElementById('reviewSummary').innerHTML = html;
    }

    // ------------------------------------------------------------------
    // Laravel validation errors land in .error-<field> elements scattered
    // across all six wizard steps - api.js populates them then fires this
    // event. Jump to whichever step holds the first one so the message the
    // user actually needs is visible, and flag every step pill that has an
    // error so nothing gets missed if there's more than one.
    // ------------------------------------------------------------------
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

    // Clear a field's server-side error the moment the user edits it, and
    // drop its step's error flag once nothing in that step is left unresolved
    // - so fixing a field doesn't require submitting again to see progress.
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

    // ------------------------------------------------------------------
    // 12. PLATFORM SWITCH TOGGLE
    // ------------------------------------------------------------------
    function updatePlatformCards() {
        document.querySelectorAll('.platform-card').forEach(card => {
            let checkbox = card.querySelector('.platform-switch');
            if (checkbox.checked) card.classList.add('active');
            else card.classList.remove('active');
        });
    }
    document.querySelectorAll('.platform-switch').forEach(item => {
        item.addEventListener('change', updatePlatformCards);
    });
    updatePlatformCards();

    // ------------------------------------------------------------------
    // 13. INIT - Set default objective
    // ------------------------------------------------------------------
    if (objectiveSelect.value) {
        objectiveSelect.dispatchEvent(new Event('change'));
    } else {
        objectiveSelect.value = 'TRAFFIC';
        objectiveSelect.dispatchEvent(new Event('change'));
    }

    showStep(1);
    }); // end window.addEventListener('load', ...)

    // ------------------------------------------------------------------
    // 14. GLOBAL VARIABLES FOR api.js
    // ------------------------------------------------------------------
    // Stays outside the load-deferred block above since api.js - a separate
    // script - reads these as globals when #campaign is submitted.
    var url = "{{ route('admin.ads.campaigns.store', ['platform' => 'tiktok']) }}";
    var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'tiktok']) }}";
    var method = 'POST';
</script>
    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
