@extends('layouts.app')

@section('title', 'Edit TikTok Campaign')

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
        display: none;
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

                        @php
                            $adGroup = $campaign->adGroups->first();
                            $creative = $adGroup?->creatives->first();
                            $ad = $campaign->ads->first();
                            $media = $creative?->media ?? collect();
                            $firstMedia = $media->first();
                            $ageGroups = json_decode($adGroup->age_groups ?? '[]', true) ?? [];
                            $languages = json_decode($adGroup->languages ?? '[]', true) ?? [];
                            $selectedCountries = json_decode($adGroup->location_ids ?? '[]', true) ?? [];
                        @endphp

                        <div class="campaign-builder">
                            <div class="builder-header">
                                <div class="social-icon-mini tiktok">
                                    <i class="bx bxl-tiktok"></i>
                                </div>
                                <h2>Edit TikTok Campaign</h2>
                                <div class="campaign-steps">
                                    <div class="step active">Campaign</div>
                                    <div class="step">Budget</div>
                                    <div class="step">Goal</div>
                                    <div class="step">Creative</div>
                                    <div class="step">Audience</div>
                                    <div class="step">Review</div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- LEFT: Form -->
                                <div class="col-lg-8">
                                    <form id="campaign" enctype="multipart/form-data">
                                        @csrf

                                        <!-- ============================================================ -->
                                        <!-- 1. CAMPAIGN INFORMATION                                       -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Campaign Information</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Campaign Name *</label>
                                                    <input type="text" name="name" id="name" value="{{ old('name', $campaign->name) }}"
                                                        class="form-control" required>
                                                    <p class="error-message error-name"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Objective *</label>
                                                    <select id="objective" name="objective" class="form-select" required>
                                                        <option value="">-- Select Objective --</option>
                                                        <option value="APP_PROMOTION" @selected(old('objective', $campaign->objective) == 'APP_PROMOTION')>App Promotion</option>
                                                        <option value="WEB_CONVERSIONS" @selected(old('objective', $campaign->objective) == 'WEB_CONVERSIONS')>Web Conversions</option>
                                                        <option value="REACH" @selected(old('objective', $campaign->objective) == 'REACH')>Reach</option>
                                                        <option value="BRAND_CONSIDERATION" @selected(old('objective', $campaign->objective) == 'BRAND_CONSIDERATION')>Brand Consideration</option>
                                                        <option value="TRAFFIC" @selected(old('objective', $campaign->objective) == 'TRAFFIC')>Traffic</option>
                                                        <option value="VIDEO_VIEWS" @selected(old('objective', $campaign->objective) == 'VIDEO_VIEWS')>Video Views</option>
                                                        <option value="ENGAGEMENT" @selected(old('objective', $campaign->objective) == 'ENGAGEMENT')>Engagement</option>
                                                        <option value="LEAD_GENERATION" @selected(old('objective', $campaign->objective) == 'LEAD_GENERATION')>Lead Generation</option>
                                                        <option value="TOPVIEW_REACH" @selected(old('objective', $campaign->objective) == 'TOPVIEW_REACH')>TopView Reach</option>
                                                    </select>
                                                    <p class="error-message error-objective"></p>
                                                </div>
                                            </div>

                                            <!-- Objective-dependent fields -->
                                            <div class="row" id="objectiveDependentFields">
                                                <div class="col-md-6 objective-app" style="display:none;">
                                                    <label>App Promotion Type</label>
                                                    <select name="app_promotion_type" id="app_promotion_type" class="form-select">
                                                        <option value="APP_INSTALL" @selected($campaign->app_promotion_type == 'APP_INSTALL')>App Install</option>
                                                        <option value="APP_RETARGETING" @selected($campaign->app_promotion_type == 'APP_RETARGETING')>App Retargeting</option>
                                                        <option value="APP_PREREGISTRATION" @selected($campaign->app_promotion_type == 'APP_PREREGISTRATION')>App Pre-registration</option>
                                                    </select>
                                                    <p class="error-message error-app_promotion_type"></p>
                                                </div>
                                                <div class="col-md-6 objective-app" style="display:none;">
                                                    <label>App ID</label>
                                                    <input type="text" name="app_id" id="app_id" value="{{ old('app_id') }}" class="form-control">
                                                    <p class="error-message error-app_id"></p>
                                                </div>
                                                <div class="col-md-6 objective-web" style="display:none;">
                                                    <label>Pixel ID</label>
                                                    <input type="text" name="pixel_id" id="pixel_id" value="{{ old('pixel_id', $adGroup->pixel_id ?? '') }}" class="form-control">
                                                    <p class="error-message error-pixel_id"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ============================================================ -->
                                        <!-- 2. BUDGET & SCHEDULE (AdGroup level)                        -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Budget & Schedule</h5>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label>Budget Mode *</label>
                                                    <select name="budget_mode" id="budget_mode" class="form-select" required>
                                                        <option value="BUDGET_MODE_DAY" @selected(old('budget_mode', $campaign->budget_mode) == 'BUDGET_MODE_DAY')>Daily Budget</option>
                                                        <option value="BUDGET_MODE_TOTAL" @selected(old('budget_mode', $campaign->budget_mode) == 'BUDGET_MODE_TOTAL')>Lifetime Budget</option>
                                                    </select>
                                                    <p class="error-message error-budget_mode"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Budget *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->currency ?? 'USD' }}</span>
                                                        <input class="form-control" name="budget" id="budget" type="number" step="0.01" min="1"
                                                            value="{{ old('budget', $campaign->budget) }}" required>
                                                    </div>
                                                    <p class="error-message error-budget"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Bid Amount</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->currency ?? 'USD' }}</span>
                                                        <input class="form-control" name="bid_amount" id="bid_amount" type="number" step="0.01"
                                                            value="{{ old('bid_amount', $adGroup->bid_price ?? '') }}">
                                                    </div>
                                                    <p class="error-message error-bid_amount"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Start Date *</label>
                                                    <input type="date" name="start_time" id="start_time" class="form-control"
                                                        value="{{ \Carbon\Carbon::parse($campaign->start_time)->format('Y-m-d') }}" required>
                                                    <p class="error-message error-start_time"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>End Date (optional)</label>
                                                    <input type="date" name="end_time" id="end_time" class="form-control"
                                                        value="{{ \Carbon\Carbon::parse($campaign->end_time)->format('Y-m-d') }}">
                                                    <p class="error-message error-end_time"></p>
                                                </div>
                                            </div>

                                            <div class="row mt-4">
                                                <div class="col-md-6 offset-md-6">
                                                    <div class="card shadow-sm border-0">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span>Budget</span>
                                                                <strong>{{ $account->currency ?? 'USD' }} <span id="budget_amount">0.00</span></strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2 text-muted">
                                                                <span>VAT (15%)</span>
                                                                <strong>{{ $account->currency ?? 'USD' }} <span id="vat_amount">0.00</span></strong>
                                                            </div>
                                                            <hr>
                                                            <input type="hidden" name="final_budget" id="final_budget" value="">
                                                            <div class="d-flex justify-content-between">
                                                                <h5 class="mb-0">Total Budget</h5>
                                                                <h5 class="mb-0 text-primary">{{ $account->currency ?? 'USD' }} <span id="total_budget">0.00</span></h5>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ============================================================ -->
                                        <!-- 3. GOAL SETUP (AdGroup level)                               -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Goal Setup</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Optimization Goal *</label>
                                                    <select name="optimization_goal" id="optimization_goal" class="form-select" required>
                                                        <option value="">-- Select Optimization Goal --</option>
                                                    </select>
                                                    <p class="error-message error-optimization_goal"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Billing Event *</label>
                                                    <select name="billing_event" id="billing_event" class="form-select" required>
                                                        <option value="">-- Select Billing Event --</option>
                                                    </select>
                                                    <p class="error-message error-billing_event"></p>
                                                </div>
                                            </div>

                                            <div class="row mt-3">
                                                <div class="col-md-6 promotion-type-block">
                                                    <label>Promotion Type *</label>
                                                    <select name="promotion_type" id="promotion_type" class="form-select" required>
                                                        <option value="">-- Select Promotion Type --</option>
                                                    </select>
                                                    <p class="error-message error-promotion_type"></p>
                                                </div>
                                                <div class="col-md-6 promotion-target-block" style="display:none;">
                                                    <label>Promotion Target Type</label>
                                                    <select name="promotion_target_type" id="promotion_target_type" class="form-select">
                                                        <option value="INSTANT_PAGE" @selected($adGroup->promotion_target_type == 'INSTANT_PAGE')>Instant Page</option>
                                                        <option value="EXTERNAL_WEBSITE" @selected($adGroup->promotion_target_type == 'EXTERNAL_WEBSITE')>External Website</option>
                                                    </select>
                                                    <p class="error-message error-promotion_target_type"></p>
                                                </div>
                                            </div>

                                            <div class="row mt-3" id="dynamicGoalFields">
                                                <div class="col-md-6 messaging-app-block" style="display:none;">
                                                    <label>Messaging App Type</label>
                                                    <select name="messaging_app_type" id="messaging_app_type" class="form-select">
                                                        <option value="">-- Select --</option>
                                                        <option value="MESSENGER">Messenger</option>
                                                        <option value="WHATSAPP">WhatsApp</option>
                                                        <option value="ZALO">Zalo</option>
                                                        <option value="LINE">Line</option>
                                                        <option value="IM_URL">Instant Messaging URL</option>
                                                    </select>
                                                    <p class="error-message error-messaging_app_type"></p>
                                                </div>
                                                <div class="col-md-6 messaging-account-block" style="display:none;">
                                                    <label>Messaging App Account ID</label>
                                                    <input type="text" name="messaging_app_account_id" id="messaging_app_account_id" class="form-control">
                                                    <p class="error-message error-messaging_app_account_id"></p>
                                                </div>
                                                <div class="col-md-6 phone-block" style="display:none;">
                                                    <label>Phone Region Code</label>
                                                    <input type="text" name="phone_region_code" id="phone_region_code" class="form-control">
                                                    <p class="error-message error-phone_region_code"></p>
                                                </div>
                                                <div class="col-md-6 phone-block" style="display:none;">
                                                    <label>Phone Number</label>
                                                    <input type="text" name="phone_number" id="phone_number" class="form-control">
                                                    <p class="error-message error-phone_number"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ============================================================ -->
                                        <!-- 4. AUDIENCE TARGETING (AdGroup level)                       -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Audience Targeting</h5>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label>Gender</label>
                                                    <select name="gender" id="gender" class="form-select">
                                                        <option value="GENDER_UNLIMITED" @selected(old('gender', $adGroup->gender) == 'GENDER_UNLIMITED')>All</option>
                                                        <option value="GENDER_MALE" @selected(old('gender', $adGroup->gender) == 'GENDER_MALE')>Male</option>
                                                        <option value="GENDER_FEMALE" @selected(old('gender', $adGroup->gender) == 'GENDER_FEMALE')>Female</option>
                                                    </select>
                                                    <p class="error-message error-gender"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Age Range</label>
                                                    <div class="checkbox-group">
                                                        @foreach ([
                                                            'AGE_18_24' => '18 – 24',
                                                            'AGE_25_34' => '25 – 34',
                                                            'AGE_35_44' => '35 – 44',
                                                            'AGE_45_54' => '45 – 54',
                                                            'AGE_55_100' => '55+',
                                                        ] as $value => $label)
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]"
                                                                    value="{{ $value }}" id="age_{{ strtolower($value) }}"
                                                                    {{ in_array($value, $ageGroups) ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="age_{{ strtolower($value) }}">{{ $label }}</label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-age_range"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Countries (multiple)</label>
                                                    <select name="countries[]" id="countries" multiple class="form-select">
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->id }}" {{ in_array($country->id, $selectedCountries) ? 'selected' : '' }}>
                                                                {{ $country->name }}
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
                                                                    <input class="form-check-input platform-switch" type="checkbox" name="languages[]"
                                                                        value="{{ $code }}" id="lang_{{ $code }}"
                                                                        {{ in_array($code, $languages) ? 'checked' : '' }}>
                                                                    <label class="form-check-label ms-2" for="lang_{{ $code }}">{{ $name }}</label>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-languages"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ============================================================ -->
                                        <!-- 5. AD CREATIVE (Ad level)                                   -->
                                        <!-- ============================================================ -->
                                        <div class="builder-card">
                                            <h5>Ad Creative</h5>
                                            <div class="duration-buttons">
                                                <input type="hidden" name="media_type" id="media_type" value="{{ $creative->type ?? 'IMAGE' }}">
                                                <button type="button" class="duration-btn media-type {{ ($creative->type ?? 'IMAGE') == 'IMAGE' ? 'active' : '' }}" data-type="IMAGE">Image</button>
                                                <button type="button" class="duration-btn media-type {{ ($creative->type ?? '') == 'CAROUSEL' ? 'active' : '' }}" data-type="CAROUSEL">Carousel</button>
                                                <button type="button" class="duration-btn media-type {{ ($creative->type ?? '') == 'VIDEO' ? 'active' : '' }}" data-type="VIDEO">Video</button>
                                                <p class="error-message error-media_type"></p>
                                            </div>
                                            <br>
                                            <div class="upload-zone">
                                                @if ($firstMedia)
                                                    @if ($firstMedia->type === 'VIDEO')
                                                        <video src="{{ $firstMedia->url }}" style="max-width:100%;border-radius:12px;" controls></video>
                                                    @else
                                                        <img src="{{ $firstMedia->url }}" style="max-width:100%;border-radius:12px;">
                                                    @endif
                                                    <p class="text-muted mt-2">Current media - upload a new file only if you want to replace it.</p>
                                                @else
                                                    <i class="bx bx-cloud-upload"></i>
                                                    <h6>Drag & Drop Media</h6>
                                                    <p>Upload image or video</p>
                                                @endif
                                                <input type="file" name="media[]" id="mediaInput" hidden accept="image/*,video/*">
                                                <button type="button" class="btn btn-primary" onclick="document.getElementById('mediaInput').click()">
                                                    {{ $firstMedia ? 'Replace Media' : 'Upload Media' }}
                                                </button>
                                                <p class="error-message error-media"></p>
                                            </div>
                                            <div class="mt-4">
                                                <label>Description</label>
                                                <textarea id="ad_description" name="description" rows="4" class="form-control">{{ old('description', $creative->message ?? '') }}</textarea>
                                                <p class="error-message error-description"></p>
                                            </div>
                                        </div>
                                        <div class="builder-card">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Target URL (Landing Page)</label>
                                                    <input type="url" name="target_link" id="target_link" class="form-control"
                                                        value="{{ old('target_link', $creative->url ?? '') }}" placeholder="https://example.com">
                                                    <p class="error-message error-target_link"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Call to Action</label>
                                                    <select name="call_to_action" id="call_to_action" class="form-select">
                                                        <option value="">-- Select CTA --</option>
                                                        @foreach ([
                                                            'LEARN_MORE' => 'Learn More', 'SHOP_NOW' => 'Shop Now', 'SIGN_UP' => 'Sign Up',
                                                            'BOOK_NOW' => 'Book Now', 'CONTACT_US' => 'Contact Us', 'CALL_NOW' => 'Call Now',
                                                            'SEND_MESSAGE' => 'Send Message', 'DOWNLOAD' => 'Download',
                                                        ] as $value => $label)
                                                            <option value="{{ $value }}" @selected(old('call_to_action', $ad->call_to_action ?? '') == $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="error-message error-call_to_action"></p>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="duration-btn active">Save Changes</button>
                                    </form>
                                </div>

                                <!-- RIGHT: Preview -->
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
                                            <img id="previewImage" class="preview-image"
                                                @if ($firstMedia && $firstMedia->type !== 'VIDEO') src="{{ $firstMedia->url }}" style="display:block" @else style="display:none" @endif>
                                            <video id="previewVideo" class="preview-image" controls
                                                @if ($firstMedia && $firstMedia->type === 'VIDEO') src="{{ $firstMedia->url }}" style="display:block;width:100%;border-radius:12px;" @else style="display:none;width:100%;border-radius:12px;" @endif></video>
                                            <div id="carouselPreview" style="display:none">
                                                <img id="carouselImage" class="preview-image">
                                                <div class="mt-3"><label>Title</label><input type="text" id="carouselTitle" class="form-control" placeholder="Card title"></div>
                                                <div class="mt-3"><label>Description</label><textarea id="carouselDescription" class="form-control" rows="3" placeholder="Card description"></textarea></div>
                                                <div class="mt-3"><label>Card URL</label><input type="url" id="carouselLink" class="form-control" placeholder="https://example.com"></div>
                                                <div class="d-flex justify-content-between mt-3">
                                                    <button type="button" class="btn btn-primary" id="prevImage">Previous</button>
                                                    <span id="carouselCounter"></span>
                                                    <button type="button" class="btn btn-primary" id="nextImage">Next</button>
                                                </div>
                                            </div>
                                            <div class="preview-content">
                                                <h6 id="previewTitle">{{ $campaign->name }}</h6>
                                                <p id="previewDescription">{{ $creative->message ?? '' }}</p>
                                            </div>
                                            <a id="previewCTA" href="{{ $creative->url ?? '#' }}" target="_blank" class="btn btn-primary w-100">
                                                {{ $ad ? ucwords(strtolower(str_replace('_', ' ', $ad->call_to_action))) : 'Learn More' }}
                                            </a>
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
    $('#countries').select2();

    const optimizationGoalBillingMap = {
        'CLICK': 'CPC', 'PAGE_VISIT': 'CPC', 'CONVERT': 'OCPM', 'INSTALL': 'OCPM',
        'IN_APP_EVENT': 'OCPM', 'TRAFFIC_LANDING_PAGE_VIEW': 'OCPM', 'LEAD_GENERATION': 'OCPM',
        'CONVERSATION': 'OCPM', 'FOLLOWERS': 'OCPM', 'VALUE': 'OCPM',
        'AUTOMATIC_VALUE_OPTIMIZATION': 'OCPM', 'PRODUCT_CLICK_IN_LIVE': 'OCPM', 'MT_LIVE_ROOM': 'OCPM',
        'DESTINATION_VISIT': 'OCPM', 'SHOW': 'CPM', 'REACH': 'CPM', 'ENGAGED_VIEW': 'CPV', 'ENGAGED_VIEW_FIFTEEN': 'CPV'
    };

    const objectiveConfig = {
        'APP_PROMOTION': { optimizationGoals: ["INSTALL", "IN_APP_EVENT", "VALUE"], promotionTypes: ['APP_ANDROID', 'APP_IOS', 'MINI_APP', 'MINI_GAME', 'GAME'] },
        'WEB_CONVERSIONS': { optimizationGoals: ["CONVERT", "VALUE", "AUTOMATIC_VALUE_OPTIMIZATION"], promotionTypes: ['WEBSITE', 'WEBSITE_OR_DISPLAY'] },
        'REACH': { optimizationGoals: ['REACH'], promotionTypes: ['WEBSITE', 'EXTERNAL_OR_DISPLAY'] },
        'BRAND_CONSIDERATION': { optimizationGoals: ['REACH', 'IMPRESSIONS', 'AD_RECALL_LIFT'], promotionTypes: ['WEBSITE', 'EXTERNAL_OR_DISPLAY'] },
        'TRAFFIC': { optimizationGoals: ["CLICK", "TRAFFIC_LANDING_PAGE_VIEW"], promotionTypes: ['WEBSITE', 'WEBSITE_OR_DISPLAY'] },
        'VIDEO_VIEWS': { optimizationGoals: ["ENGAGED_VIEW", "ENGAGED_VIEW_FIFTEEN"], promotionTypes: ['WEBSITE', "WEBSITE_OR_DISPLAY"] },
        'ENGAGEMENT': { optimizationGoals: ["FOLLOWERS", "PAGE_VISIT"], promotionTypes: ['EXTERNAL_OR_DISPLAY', 'WEBSITE'] },
        'LEAD_GENERATION': { optimizationGoals: ['LEAD_GENERATION'], promotionTypes: ['LEAD_GENERATION', 'LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE', 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE', 'LEAD_GEN_CLICK_TO_CALL'] },
        'TOPVIEW_REACH': { optimizationGoals: ['REACH', 'IMPRESSIONS'], promotionTypes: ['WEBSITE', 'EXTERNAL_OR_DISPLAY'] }
    };

    const promotionTypeLabels = {
        'APP_ANDROID': 'Android App', 'APP_IOS': 'iOS App', 'MINI_APP': 'Mini App', 'MINI_GAME': 'Mini Game', 'GAME': 'Game',
        'WEBSITE': 'Website', 'LEAD_GENERATION': 'Lead Generation (Instant Form/Website)',
        'LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE': 'Lead via TikTok DM', 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE': 'Lead via Social Media App Message',
        'LEAD_GEN_CLICK_TO_CALL': 'Lead via Phone Call', 'WEBSITE_OR_DISPLAY': 'Website or Display', 'EXTERNAL_OR_DISPLAY': 'External or Display',
    };

    const qs = document.querySelector.bind(document);
    const objectiveSelect = qs('#objective');
    const optGoalSelect = qs('#optimization_goal');
    const billingEventSelect = qs('#billing_event');
    const promotionTypeSelect = qs('#promotion_type');
    const callToActionSelect = qs('#call_to_action');
    const targetLinkInput = qs('#target_link');
    const previewCTA = document.getElementById('previewCTA');

    const selectedOptimizationGoal = "{{ $adGroup->optimization_goal ?? '' }}";
    const selectedPromotionType = "{{ $adGroup->promotion_type ?? '' }}";

    function beautifyLabel(value) {
        return value.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
    }

    function populateFields(objective, preselect = false) {
        const config = objectiveConfig[objective];
        if (!config) return;

        const goalOptions = config.optimizationGoals.map(g => `<option value="${g}">${beautifyLabel(g)}</option>`).join('');
        optGoalSelect.innerHTML = `<option value="">-- Select Optimization Goal --</option>${goalOptions}`;
        optGoalSelect.disabled = false;

        const promOptions = config.promotionTypes.map(p => `<option value="${p}">${promotionTypeLabels[p] || p}</option>`).join('');
        promotionTypeSelect.innerHTML = `<option value="">-- Select Promotion Type --</option>${promOptions}`;
        promotionTypeSelect.disabled = false;

        document.querySelectorAll('.objective-app').forEach(el => el.style.display = objective === 'APP_PROMOTION' ? '' : 'none');
        document.querySelectorAll('.objective-web').forEach(el => el.style.display = objective === 'WEB_CONVERSIONS' ? '' : 'none');

        if (preselect) {
            optGoalSelect.value = selectedOptimizationGoal;
            optGoalSelect.dispatchEvent(new Event('change'));
            promotionTypeSelect.value = selectedPromotionType;
        } else {
            optGoalSelect.value = '';
            billingEventSelect.innerHTML = '<option value="">-- Select Billing Event --</option>';
            billingEventSelect.disabled = true;
        }

        promotionTypeSelect.dispatchEvent(new Event('change'));
    }

    optGoalSelect.addEventListener('change', function () {
        const goal = this.value;
        if (goal && optimizationGoalBillingMap.hasOwnProperty(goal)) {
            const billing = optimizationGoalBillingMap[goal];
            billingEventSelect.innerHTML = `<option value="${billing}">${billing}</option>`;
            billingEventSelect.value = billing;
            billingEventSelect.disabled = false;
        } else {
            billingEventSelect.innerHTML = '<option value="">-- Select Billing Event --</option>';
            billingEventSelect.disabled = true;
        }
    });

    promotionTypeSelect.addEventListener('change', function () {
        const promType = this.value;
        const showPromoTarget = ['LEAD_GENERATION', 'LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE', 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE', 'LEAD_GEN_CLICK_TO_CALL'].includes(promType);
        const targetBlock = document.querySelector('.promotion-target-block');
        if (targetBlock) targetBlock.style.display = showPromoTarget ? '' : 'none';

        const showMessaging = promType === 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE';
        const msgBlock = document.querySelector('.messaging-app-block');
        if (msgBlock) msgBlock.style.display = showMessaging ? '' : 'none';
        if (!showMessaging) {
            const accBlock = document.querySelector('.messaging-account-block');
            if (accBlock) accBlock.style.display = 'none';
            document.querySelectorAll('.phone-block').forEach(el => el.style.display = 'none');
        }
    });

    objectiveSelect.addEventListener('change', function () {
        populateFields(this.value, false);
    });

    function calculateBudget() {
        let budgetMode = document.getElementById('budget_mode').value;
        let budget = parseFloat(document.getElementById('budget').value) || 0;
        let startDate = document.getElementById('start_time').value;
        let endDate = document.getElementById('end_time').value;
        let allocatedBudget = budget;

        if (budgetMode === 'BUDGET_MODE_DAY' && startDate && endDate) {
            let days = Math.ceil((new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24)) + 1;
            allocatedBudget = days > 0 ? budget * days : 0;
        }

        let vat = allocatedBudget * 0.15;
        let total = allocatedBudget + vat;
        document.getElementById('budget_amount').innerText = allocatedBudget.toFixed(2);
        document.getElementById('vat_amount').innerText = vat.toFixed(2);
        document.getElementById('total_budget').innerText = total.toFixed(2);
        document.getElementById('final_budget').value = total.toFixed(2);
    }

    ['budget', 'budget_mode', 'start_time', 'end_time'].forEach(id => {
        document.getElementById(id).addEventListener(id === 'budget' ? 'input' : 'change', calculateBudget);
    });

    document.getElementById('name').addEventListener('keyup', function () {
        document.getElementById('previewTitle').innerText = this.value || 'Campaign Name';
    });
    document.getElementById('ad_description').addEventListener('keyup', function () {
        document.getElementById('previewDescription').innerText = this.value || 'Ad description';
    });
    targetLinkInput.addEventListener('input', function () {
        previewCTA.href = this.value || '#';
    });
    callToActionSelect.addEventListener('change', function () {
        previewCTA.innerText = this.options[this.selectedIndex]?.text || 'Learn More';
    });

    let creativeType = document.getElementById('media_type').value;
    let carouselItems = [];
    let currentIndex = 0;
    const mediaInput = document.getElementById('mediaInput');
    const carouselDiv = document.getElementById('carouselPreview');

    document.querySelectorAll('.media-type').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.media-type').forEach(x => x.classList.remove('active'));
            this.classList.add('active');
            creativeType = this.dataset.type;
            document.getElementById('media_type').value = creativeType;

            if (creativeType === 'CAROUSEL') {
                mediaInput.setAttribute('multiple', true);
                mediaInput.accept = "image/*";
                carouselItems = [];
                currentIndex = 0;
            } else if (creativeType === 'IMAGE') {
                mediaInput.removeAttribute('multiple');
                mediaInput.accept = "image/*";
            } else {
                mediaInput.removeAttribute('multiple');
                mediaInput.accept = "video/*";
            }
        });
    });

    function loadCarouselItem() {
        if (!carouselItems.length) return;
        let item = carouselItems[currentIndex];
        document.getElementById('carouselImage').src = item.image;
        document.getElementById('carouselTitle').value = item.title || '';
        document.getElementById('carouselDescription').value = item.description || '';
        document.getElementById('carouselLink').value = item.link || '';
        document.getElementById('carouselCounter').innerHTML = `${currentIndex + 1} / ${carouselItems.length}`;
    }

    document.getElementById('carouselTitle').addEventListener('input', function () { if (carouselItems[currentIndex]) carouselItems[currentIndex].title = this.value; });
    document.getElementById('carouselDescription').addEventListener('input', function () { if (carouselItems[currentIndex]) carouselItems[currentIndex].description = this.value; });
    document.getElementById('carouselLink').addEventListener('input', function () { if (carouselItems[currentIndex]) carouselItems[currentIndex].link = this.value; });

    mediaInput.addEventListener('change', function (e) {
        let files = Array.from(e.target.files);
        let image = document.getElementById('previewImage');
        let video = document.getElementById('previewVideo');
        image.style.display = 'none';
        video.style.display = 'none';
        carouselDiv.style.display = 'none';

        if (creativeType === 'CAROUSEL') {
            carouselItems = files.map(file => ({ image: URL.createObjectURL(file), title: '', description: '', link: '' }));
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

    document.getElementById('nextImage').addEventListener('click', function () { if (currentIndex < carouselItems.length - 1) { currentIndex++; loadCarouselItem(); } });
    document.getElementById('prevImage').addEventListener('click', function () { if (currentIndex > 0) { currentIndex--; loadCarouselItem(); } });

    document.querySelectorAll('.step').forEach(step => {
        step.addEventListener('click', function () {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            this.classList.add('active');
        });
    });

    function updatePlatformCards() {
        document.querySelectorAll('.platform-card').forEach(card => {
            let checkbox = card.querySelector('.platform-switch');
            if (checkbox.checked) card.classList.add('active'); else card.classList.remove('active');
        });
    }
    document.querySelectorAll('.platform-switch').forEach(item => item.addEventListener('change', updatePlatformCards));
    updatePlatformCards();

    // Initial population, preselecting the campaign's existing values
    populateFields(objectiveSelect.value, true);
    calculateBudget();

    var campaignId = @json($campaign->id);
    var url = "{{ route('admin.ads.campaigns.update', ['platform' => 'tiktok', 'campaign' => $campaign->id]) }}";
    var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'tiktok']) }}";
    var method = 'PUT';
</script>
<script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
