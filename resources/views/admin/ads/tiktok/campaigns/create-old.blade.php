@extends('layouts.app')

@section('title', 'Dashboard')
<style>
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
    }

    .step {
        background: #f3f5f8;
        padding: 10px 20px;
        border-radius: 30px;
    }

    .step {
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
        min-width: 220px;
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
</style>
@section('content')
    <div class="col-xxl-12 mb-0">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <div class="card px-sm-6 px-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-end mb-3">
                            <a href="{{ route('admin.ads.campaigns.index', ['platform' => $platform]) }}">
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
                                <h2>Create Tiktok Campaign</h2>
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
                                <!-- LEFT SIDE -->
                                <div class="col-lg-8">
                                    <form id="campaign">
                                        <div class="builder-card">
                                            <h5>Campaign Information</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Campaign Name</label>
                                                    <input type="text" name="name" id="name"
                                                        class="form-control">
                                                    <p class="error-message error-name"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Objective</label>
                                                    <select id="objective" name="objective" class="form-select">
                                                        <option value="APP_PROMOTION">APP PROMOTION</option>
                                                        <option value="WEB_CONVERSIONS">WEB CONVERSIONS</option>
                                                        <option value="REACH">REACH</option>
                                                        <option value="BRAND_CONSIDERATION">BRAND CONSIDERATION</option>
                                                        <option value="TRAFFIC">TRAFFIC</option>
                                                        <option value="VIDEO_VIEWS">VIDEO VIEWS</option>
                                                        <option value="ENGAGEMENT">ENGAGEMENT</option>
                                                        <option value="LEAD_GENERATION">LEAD GENERATION</option>
                                                        <option value="TOPVIEW_REACH">TOP VIEW REACH</option>
                                                    </select>
                                                    <p class="error-message error-objective"></p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 APP_PROMOTION" style="display:none">
                                                    <label>App Promotion Type</label>
                                                    <select id="app_promotion_type" name="app_promotion_type" class="form-select">
                                                        <option value="APP_INSTALL">APP INSTALL</option>
                                                        <option value="APP_RETARGETING">APP RETARGETING</option>
                                                        <option value="APP_PREREGISTRATION">APP PRE REGISTRATION</option>
                                                    </select>
                                                    <p class="error-message error-app_promotion_type"></p>
                                                </div>
                                                <div class="col-md-6 app" style="display:none">
                                                    <label>App Id</label>
                                                    <input type="text" name="app_id" id="app_id" class="form-control">
                                                    <p class="error-message error-app_id"></p>
                                                </div>

                                                <div class="col-md-6 promotion_website_type" style="display:none">
                                                    <label>App Promotion Type</label>
                                                    <select id="promotion_website_type" name="promotion_website_type" class="form-select">
                                                        <option value="UNSET">Unset</option>
                                                        <option value="TIKTOK_NATIVE_PAGE">Tiktok Native Page</option>
                                                    </select>
                                                    <p class="error-message error-promotion_website_type"></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="builder-card">
                                            <h5>Budget & Schedule</h5>
                                            <div class="row mt-4">
                                                <div class="col-md-6">
                                                    <label>Start Date</label>
                                                    <input type="date" id="start_time" name="start_time"
                                                        class="form-control">
                                                    <p class="error-message error-start_time"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>End Date</label>
                                                    <input type="date" id="end_time" name="end_time"
                                                        class="form-control">
                                                    <p class="error-message error-end_time"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-4">
                                                <div class="col-md-4">
                                                    <label>Budget Type</label>
                                                    <select class="form-select" name="budget_mode" id="budget_mode">
                                                        <option value="daily_budget">Daily Budget</option>
                                                        <option value="lifetime_budget">Lifetime Budget</option>
                                                    </select>
                                                    <p class="error-message error-budget_mode"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Budget</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->metadata['currency'] ?? null }}</span>
                                                        <input class="form-control" name="budget" id="budget"
                                                            type="number" step="0.01">
                                                    </div>
                                                    <p class="error-message error-budget"></p>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Bid Amount</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->metadata['currency'] ?? null }}</span>
                                                        <input class="form-control" name="bid_amount" id="bid_amount"
                                                            type="number" step="0.01">
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
                                                                <strong>
                                                                    {{ $account->metadata['currency'] ?? null }}
                                                                    <span id="budget_amount">0.00</span>
                                                                </strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2 text-muted">
                                                                <span>VAT (15%)</span>
                                                                <strong>
                                                                    {{ $account->metadata['currency'] ?? null }}
                                                                    <span id="vat_amount">0.00</span>
                                                                </strong>
                                                            </div>
                                                            <hr>
                                                            <input type="hidden" name="final_budget"
                                                            id="final_budget" value="">
                                                            <div class="d-flex justify-content-between">
                                                                <h5 class="mb-0">Total Budget</h5>
                                                                <h5 class="mb-0 text-primary">
                                                                    {{ $account->metadata['currency'] ?? null }}
                                                                    
                                                                    <span id="total_budget">0.00</span>
                                                                </h5>
                                                            </div>


                                                        </div>
                                                    </div>

                                                </div>

                                            </div>

                                        </div>
                                        <div class="builder-card">
                                            <h5>Goal Setup</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Optimization Gaol</label>
                                                    <select id="optimization_goal" name="optimization_goal"
                                                        class="form-select">
                                                    </select>
                                                    <p class="error-message error-optimization_goal"></p>
                                                </div>
                                                <div class="col-md-6 promotion_type">
                                                    <label>Promotion Type</label>
                                                    <select name="promotion_type" id="promotion_type"
                                                        class="form-select">
                                                        <option value="APP_ANDROID">Android application</option>
                                                        <option value="APP_IOS">iOS application</option>
                                                        <option value="MINI_APP">TikTok Minis of the mini series type.</option>
                                                        <option value="MINI_GAME">TikTok Minis of the mini game type.</option>
                                                        <option value="GAME">Game</option>
                                                        <option value="WEBSITE">Website - Landing Page</option>
                                                        <option value="LEAD_GENERATION">Instant Form or Website for Lead generation ads.</option>
                                                        <option value="LEAD_GEN_CLICK_TO_TT_DIRECT_MESSAGE">Collect leads by TikTok direct messages.</option>
                                                        <option value="LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE">Collect leads by instant messaging apps.</option>
                                                        <option value="LEAD_GEN_CLICK_TO_CALL">Collect leads by phone call.</option>
                                                        <option value="WEBSITE_OR_DISPLAY">Landing page or pure display page</option>
                                                        <option value="TIKTOK_SHOP">TikTok shop / store</option>
                                                        <option value="VIDEO_SHOPPING">Video Shopping</option>
                                                        <option value="PRODUCT_SHOPPING_ADS">Product Shopping ads</option>
                                                        <option value="PSA_PRODUCT">Psa Product</option>
                                                        <option value="EXTERNAL_OR_DISPLAY" disabled>External or Display</option>
                                                    </select>
                                                    <p class="error-message error-promotion_type"></p>
                                                </div>
                                                <div class="col-md-6 pixel_id" style="display:none">
                                                    <label>Pixel Id</label>
                                                    <input type="text" name="pixel_id" id="pixel_id" class="form-control">
                                                    <p class="error-message error-pixel_id"></p>
                                                </div>
                                                <div class="col-md-6 promotion_target_type">
                                                    <label>Promotion Target Type</label>
                                                    <select name="promotion_target_type" id="promotion_target_type"
                                                        class="form-select">
                                                        <option value="INSTANT_PAGE">Instant Form. To create a fast-loading in-app TikTok Instant Form to collect more leads</option>
                                                        <option value="EXTERNAL_WEBSITE">Website Form. To use a landing page that has the Website Form or the TikTok Instant Page that redirects to the website with the Website Form to collect more leads.</option>
                                                    </select>
                                                    <p class="error-message error-promotion_target_type"></p>
                                                </div>
                                                <div class="col-md-6 messaging_app_type" style="display:none">
                                                    <label>Messaging App Type</label>
                                                    <select name="messaging_app_type" id="messaging_app_type"
                                                        class="form-select">
                                                        <option value="MESSENGER">Messenger</option>
                                                        <option value="phone_region_code">WhatsApp</option>
                                                        <option value="ZALO">Zalo</option>
                                                        <option value="WHATSAPP">Line</option>
                                                        <option value="IM_URL">Instant Messaging URL</option>
                                                    </select>
                                                    <p class="error-message error-messaging_app_type"></p>
                                                </div>
                                                <div class="col-md-6 messaging_app_account_id" style="display:none">
                                                    <label>Messaging App Type</label>
                                                    <input type="text" name="messaging_app_account_id" id="messaging_app_account_id"
                                                    class="form-control">
                                                    <p class="error-message error-messaging_app_account_id"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Destination Type</label>
                                                    <select name="destination_type" id="destination_type"
                                                        class="form-select">
                                                    </select>
                                                    <p class="error-message error-destination_type"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Billing Event</label>
                                                    <select id="billing_event" name="billing_event" class="form-select">
                                                    </select>
                                                    <p class="error-message error-billing_event"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Countries</label>
                                                    <select id="countries" name="countries[]" multiple
                                                        class="form-select">
                                                        @foreach ($countries as $country)
                                                            <option value="{{ $country->id }}">{{ $country->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <p class="error-message error-countries"></p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Page Id</label>
                                                    <div class="input-group">
                                                        <input class="form-control" name="page_id" id="page_id"
                                                            type="text" step="0.01">
                                                    </div>
                                                    <p class="error-message error-page_id"></p>
                                                </div>
                                                <div class="col-md-6" style="display:none">
                                                    <label>Shopping Ads Type</label>
                                                    <select id="shopping_ads_type" name="shopping_ads_type"
                                                        class="form-select">
                                                        <option value="VIDEO">VIDEO</option>
                                                        <option value="LIVE">LIVE</option>
                                                        <option value="PRODUCT_SHOPPING_ADS">PRODUCT SHOPPING ADS</option>
                                                    </select>
                                                    <p class="error-message error-shopping_ads_type"></p>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="builder-card">

                                            <h5>Ad Creative</h5>
                                            <div class="duration-buttons">
                                                <input type="hidden" name="media_type" id="media_type" value="IMAGE">
                                                <button type="button" class="duration-btn media-type active"
                                                    data-type="IMAGE">
                                                    Image
                                                </button>

                                                <button type="button" class="duration-btn media-type"
                                                    data-type="CAROUSEL">
                                                    Carousel
                                                </button>

                                                <button type="button" class="duration-btn media-type" data-type="VIDEO">
                                                    Video
                                                </button>
                                                <p class="error-message error-media_type"></p>
                                            </div>
                                            <br>
                                            <div class="upload-zone">
                                                <i class="bx bx-cloud-upload"></i>

                                                <h6>Drag & Drop Media</h6>

                                                <p>Upload image or video</p>

                                                <input type="file" name="media[]" id="mediaInput" hidden
                                                    accept="image/*,video/*">

                                                <button type="button" class="btn btn-primary"
                                                    onclick="mediaInput.click()">
                                                    Upload Media
                                                </button>
                                                <p class="error-message error-media"></p>
                                            </div>


                                            <div class="mt-4">

                                                <label>Description</label>

                                                <textarea id="adDescription" name="description" rows="4" class="form-control"></textarea>
                                                <p class="error-message error-description"></p>
                                            </div>
                                            <div class="mt-4">
                                                <label>Target URL</label>

                                                <input type="url" name="target_link" id="targetLink"
                                                    class="form-control" placeholder="https://example.com">
                                                <p class="error-message error-target_link"></p>
                                            </div>

                                        </div>
                                        <div class="builder-card">
                                            <h5>Audience</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <select id="call_to_action" name="call_to_action"
                                                        class="form-select">
                                                        <option value="">Call To Action</option>
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
                                                <div class="col-md-6">
                                                    <select id="gender" name="gender" class="form-select">
                                                        <option value="">Gender</option>
                                                        <option value="male">Male</option>
                                                        <option value="female">Female</option>
                                                        <option value="both">Both</option>
                                                    </select>
                                                    <p class="error-message error-gender"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <select id="age_from" name="age_from" class="form-select">
                                                        <option value="">Age From</option>
                                                        <option value="18">18</option>
                                                        <option value="19">19</option>
                                                        <option value="20">20</option>
                                                        <option value="21">21</option>
                                                        <option value="22">22</option>
                                                        <option value="23">23</option>
                                                        <option value="24">24</option>
                                                        <option value="25">25</option>
                                                        <option value="26">26</option>
                                                        <option value="27">27</option>
                                                        <option value="28">28</option>
                                                        <option value="29">29</option>
                                                        <option value="30">30</option>
                                                    </select>
                                                    <p class="error-message error-age_from"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <select id="age_to" name="age_to" class="form-select">
                                                        <option value="">Age To</option>
                                                        <option value="31">31</option>
                                                        <option value="32">32</option>
                                                        <option value="33">33</option>
                                                        <option value="34">34</option>
                                                        <option value="35">35</option>
                                                        <option value="36">36</option>
                                                        <option value="37">37</option>
                                                        <option value="38">38</option>
                                                        <option value="39">39</option>
                                                        <option value="40">40</option>
                                                        <option value="41">41</option>
                                                        <option value="42">42</option>
                                                        <option value="43">43</option>
                                                        <option value="44">44</option>
                                                        <option value="45">45</option>
                                                        <option value="45+">45+</option>
                                                    </select>
                                                    <p class="error-message error-age_to"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <label>Languages</label>

                                                    <div class="platform-group">

                                                        <div class="platform-card">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch"
                                                                    type="checkbox" name="languages[]" value="english"
                                                                    id="english" checked>

                                                                <label class="form-check-label ms-2" for="english">
                                                                    English
                                                                </label>
                                                                <p class="error-message error-languages"></p>
                                                            </div>
                                                        </div>

                                                        <div class="platform-card">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch"
                                                                    type="checkbox" id="instagramPlatform"
                                                                    name="languages[]" value="arabic">

                                                                <label class="form-check-label ms-2" for="arabic">
                                                                    Arabic
                                                                </label>
                                                                <p class="error-message error-languages"></p>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <button type="submit" class="duration-btn active">
                                            Launch
                                        </button>
                                    </form>
                                </div>
                                <!-- RIGHT SIDE -->
                                <div class="col-lg-4">
                                    <div class="preview-card">
                                        <div class="preview-header">
                                            Live Preview
                                        </div>
                                        <div class="facebook-preview">
                                            <div class="preview-top">
                                                <div class="avatar"></div>
                                                <div>
                                                    <strong>{{ $account->name }}</strong>
                                                    <div>Sponsored</div>
                                                </div>
                                            </div>
                                            <img id="previewImage" class="preview-image" style="display:none">
                                            <video id="previewVideo" class="preview-image" controls
                                                style="display:none;width:100%;border-radius:12px;">
                                            </video>
                                            <div id="carouselPreview" style="display:none">
                                                <img id="carouselImage" class="preview-image">
                                                <div class="mt-3">
                                                    <label>Title</label>
                                                    <input type="text" id="carouselTitle" class="form-control"
                                                        placeholder="Card title">
                                                </div>
                                                <div class="mt-3">
                                                    <label>Description</label>
                                                    <textarea id="carouselDescription" class="form-control" rows="3" placeholder="Card description"></textarea>
                                                </div>
                                                <div class="mt-3">
                                                    <label>Card URL</label>
                                                    <input type="url" id="carouselLink" class="form-control"
                                                        placeholder="https://example.com">
                                                </div>
                                                <div class="d-flex justify-content-between mt-3">
                                                    <button type="button" class="btn btn-primary" id="prevImage">
                                                        Previous
                                                    </button>
                                                    <span id="carouselCounter"></span>
                                                    <button type="button" class="btn btn-primary" id="nextImage">
                                                        Next
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="preview-content">
                                                <h6 id="previewTitle">
                                                    Campaign Name
                                                </h6>
                                                <p id="previewDescription">
                                                    Ad description will appear here...
                                                </p>
                                            </div>
                                            <a id="previewCTA" href="#" target="_blank"
                                                class="btn btn-primary w-100">
                                                Learn More
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
        var areYouSure = "{{ __('admin.sweet-alert.are-you-sure') }}";
        var YouWontBeAbleToRevertThis = "{{ __('admin.sweet-alert.you-wont-be-able-to-revert-this') }}";
        var YesDeleteIt = "{{ __('admin.sweet-alert.yes-delete-it') }}";
        var recordHasBeenDelete = "{{ __('admin.sweet-alert.record-has-been-deleted') }}";
        var deleted = "{{ __('admin.sweet-alert.deleted') }}";
        var saveDescription = "{{ __('admin.sweet-alert.save-description') }}";
        var saveHeader = "{{ __('admin.sweet-alert.save-header') }}";
        var saveHeader = "{{ __('admin.sweet-alert.save-header') }}";
        var dontSave = "{{ __('admin.sweet-alert.dont-save') }}";
        var wentWrong = "{{ __('admin.sweet-alert.went-wrong') }}";
        var error = "{{ __('admin.sweet-alert.error') }}";
        var success = "{{ __('admin.sweet-alert.success') }}";
        var changesNotSaved = "{{ __('admin.sweet-alert.changes-not-saved') }}";
        var apiUrl = "{{ route('admin.apis.store') }}";
        var getAPIUrl = "{{ route('admin.apis.show', ['api' => ':API']) }}";
        var updateAPIUrl = "{{ route('admin.apis.update', ['api' => ':API']) }}";
        var destroyAPIUrl = "{{ route('admin.apis.destroy', ['api' => ':API']) }}";
        var url = "{{ route('admin.ads.campaigns.store', ['platform' => 'facebook']) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'facebook']) }}";
        var method = 'POST';
        var edit = "{{ __('admin.table.edit') }}";
        var deletebutton = "{{ __('admin.table.delete') }}";
        $('#countries').select2();
        document.getElementById('name').addEventListener('keyup', function() {

            document.getElementById('previewTitle')
                .innerText = this.value || 'Campaign Name';

        });

        document.getElementById('adDescription').addEventListener('keyup', function() {

            document.getElementById('previewDescription')
                .innerText = this.value || 'Ad description';

        });

        let creativeType = 'IMAGE';
        let carouselItems = [];
        let currentIndex = 0;
        let mediaInput = document.getElementById('mediaInput');
        let carousel = document.getElementById('carouselPreview');



        document.querySelectorAll('.media-type').forEach(btn => {
            btn.addEventListener('click', function() {

                document.querySelectorAll('.media-type')
                    .forEach(x => x.classList.remove('active'));

                this.classList.add('active');

                creativeType = this.dataset.type;

                let input = document.getElementById('mediaInput');
                $('#media_type').val(creativeType);
                console.log($('#media_type').val());
                if (creativeType === 'CAROUSEL') {

                    input.setAttribute('multiple', true);
                    input.accept = "image/*";

                    carouselItems = [];
                    currentIndex = 0;

                } else if (creativeType === 'IMAGE') {

                    input.removeAttribute('multiple');
                    input.accept = "image/*";

                } else {

                    input.removeAttribute('multiple');
                    input.accept = "video/*";
                }
            });
        });

        function loadCarouselItem() {

            if (!carouselItems.length) return;

            let item = carouselItems[currentIndex];

            document.getElementById('carouselImage').src = item.image;
            document.getElementById('carouselTitle').value = item.title;
            document.getElementById('carouselDescription').value = item.description;
            document.getElementById('carouselLink').value = item.link;

            document.getElementById('carouselCounter').innerHTML =
                `${currentIndex + 1} / ${carouselItems.length}`;
        }
        document.getElementById('carouselTitle').addEventListener('input', function() {


            carouselItems[currentIndex].title = this.value;


        });
        document.getElementById('carouselDescription').addEventListener('input', function() {
            carouselItems[currentIndex].description = this.value;
        });
        document.getElementById('carouselLink').addEventListener('input', function() {
            carouselItems[currentIndex].link = this.value;
        });
        document.getElementById('mediaInput')
            .addEventListener('change', function(e) {

                let files = Array.from(e.target.files);

                let image = document.getElementById('previewImage');
                let video = document.getElementById('previewVideo');

                image.style.display = 'none';
                video.style.display = 'none';
                carousel.style.display = 'none';

                if (creativeType === 'CAROUSEL') {

                    carouselItems = files.map(file => ({
                        image: URL.createObjectURL(file),
                        title: '',
                        description: '',
                        link: ''
                    }));

                    currentIndex = 0;

                    loadCarouselItem();
                    carousel.style.display = 'block';

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
            if (currentIndex < carouselItems.length - 1) {
                currentIndex++;
                loadCarouselItem();
            }
        });
        document.getElementById('prevImage').addEventListener('click', function() {
            if (currentIndex > 0) {
                currentIndex--;
                loadCarouselItem();
            }
        });
        document.getElementById('targetLink').addEventListener('keyup', function() {

            document.getElementById('previewCTA')
                .href = this.value || '#';

        });
        document.querySelectorAll('.duration-btn').forEach(btn => {

            btn.addEventListener('click', function() {

                document.querySelectorAll('.duration-btn').forEach(item => item.classList.remove('active'));

                this.classList.add('active');
            });
        });
        document.querySelectorAll('.step').forEach(step => {

            step.addEventListener('click', function() {

                document.querySelectorAll('.step').forEach(item => item.classList.remove('active'));

                this.classList.add('active');

            });

        });

        document.getElementById('call_to_action').addEventListener('change', function() {
            let text = this.options[this.selectedIndex].text;

            document.getElementById('previewCTA')
                .innerText = text;

        });

        function updatePlatformCards() {
            document.querySelectorAll('.platform-card').forEach(card => {

                let checkbox = card.querySelector('.platform-switch');

                if (checkbox.checked) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }

            });
        }

        document.querySelectorAll('.platform-switch').forEach(item => {

            item.addEventListener('change', updatePlatformCards);

        });

        updatePlatformCards();


        const objectiveMap = {

            OUTCOME_AWARENESS: {
                destinationTypes: [],
                optimizationGoals: [
                    'REACH',
                    'IMPRESSIONS',
                    'AD_RECALL_LIFT'
                ],
                billingEvents: [
                    'IMPRESSIONS'
                ],
                ctas: [
                    'LEARN_MORE'
                ]
            },

            TRAFFIC: {
                destinationTypes: [
                    'WEBSITE',
                    'APP',
                    'MESSENGER',
                    'WHATSAPP'
                ],
                optimizationGoals: [
                    'LINK_CLICKS',
                    'LANDING_PAGE_VIEWS'
                ],
                billingEvents: [
                    'IMPRESSIONS'
                ],
                ctas: [
                    'LEARN_MORE',
                    'SHOP_NOW',
                    'CONTACT_US'
                ]
            },

            OUTCOME_ENGAGEMENT: {
                destinationTypes: [
                    'MESSENGER',
                    'WHATSAPP'
                ],
                optimizationGoals: [
                    'POST_ENGAGEMENT',
                    'VIDEO_VIEWS',
                    'THRUPLAY'
                ],
                billingEvents: [
                    'IMPRESSIONS'
                ],
                ctas: [
                    'SEND_MESSAGE'
                ]
            },

            OUTCOME_LEADS: {
                conversionLocations: [
                    'WEBSITE',
                    'INSTANT_FORM',
                    'MESSENGER',
                    'WHATSAPP'
                ],
                optimizationGoals: [
                    'LEADS',
                    'QUALITY_LEADS'
                ],
                billingEvents: [
                    'IMPRESSIONS'
                ],
                ctas: [
                    'SIGN_UP',
                    'APPLY_NOW',
                    'BOOK_NOW'
                ]
            },

            APP_PROMOTION: {
                destinationTypes: [
                    'APP'
                ],
                optimizationGoals: [
                    'APP_INSTALLS',
                    'APP_EVENTS'
                ],
                billingEvents: [
                    'IMPRESSIONS'
                ],
                ctas: [
                    'DOWNLOAD'
                ]
            },

            OUTCOME_SALES: {
                conversionLocations: [
                    'WEBSITE',
                    'APP',
                    'SHOP'
                ],
                optimizationGoals: [
                    'OFFSITE_CONVERSIONS',
                    'PURCHASE'
                ],
                billingEvents: [
                    'IMPRESSIONS'
                ],
                ctas: [
                    'SHOP_NOW',
                    'BUY_NOW'
                ]
            }
        };
        //Promotion Type hide/show
        $('#promotion_type').on('change', function () {
            const promotionType = $(this).val();
            const optimizationGoal = $('#optimization_goal').val();

            $('.messaging_app_type').toggle(
                promotionType === 'LEAD_GEN_CLICK_TO_SOCIAL_MEDIA_APP_MESSAGE' &&
                ['CLICK', 'CONVERSATION'].includes(optimizationGoal)
            );
        });
                
        // Optimization Goal Properties
        $('#optimization_goal').on('change', function () {
            const isConversation = $(this).val() === 'CONVERSATION';
            const options = ['ZALO', 'LINE', 'IM_URL'];

            options.forEach(function (value) {
                $('#messaging_app_type option[value="' + value + '"]')
                    .prop('disabled', isConversation);
            });

            $('#pixel_id').toggle(
                ['CONVERT', 'VALUE'].includes($(this).val())
            );

            
        });

        // App promotion type Properties
        $('#app_promotion_type').on('change', function () {
            const appPromotionType = $(this).val();
            const objective = $('#objective').val();
            $('#promotion_website_type').toggle(
                ['APP_PREREGISTRATION'].includes(appPromotionType)
            );
            $('.app').hide();
            if (objective == 'objective_type' && ['APP_RETARGETING', 'APP_INSTALL'].includes(appPromotionType)) {
                $('.app').show();
            }
        });

        // Optimization Goal Properties
        $('#messaging_app_type').on('change', function () {
            const messagingAppType = $(this).val();

            $('#messaging_app_account_id').toggle(
                ['MESSENGER', 'LINE'].includes(messagingAppType)
            );

            $('#phone_region_code').toggle(
                ['WHATSAPP', 'ZALO'].includes(messagingAppType)
            );

            $('#phone_region_calling_code').toggle(
                ['WHATSAPP', 'ZALO'].includes(messagingAppType)
            );

            $('#phone_number').toggle(
                ['WHATSAPP', 'ZALO'].includes(messagingAppType)
            );

        });

        populateFields(objectiveMap['TRAFFIC']);
        $('#objective').on('change', function() {
            let objective = $(this).val();
           
            populateFields(objectiveMap[objective]);

            $('#promotion_type option').prop('disabled', false);

            if (['REACH', 'VIDEO_VIEWS'].includes(objective)) {

                // Hide promotion_type completely
                $('.promotion_type').hide();

            } else if (objective === 'ENGAGEMENT') {
                // Show promotion_type
                $('.promotion_type').show();

                // Disable all options except EXTERNAL_OR_DISPLAY
                $('#promotion_type option').prop('disabled', true);
                $('#promotion_type option[value="EXTERNAL_OR_DISPLAY"]').prop('disabled', false);

                // Select EXTERNAL_OR_DISPLAY automatically
                $('#promotion_type').val('EXTERNAL_OR_DISPLAY');

            } else if (objective === 'ENGAGEMENT'){
                $('.promotion_target_type').show();

            } else {
                // Show promotion_type for all other objectives
                $('.promotion_type').show();
                // Enable all options
                $('#promotion_type option').prop('disabled', false);
            }



        });

        function populateFields(data) {
            console.log(data);
           // var billingEvents = data['billingEvents'];
            let options = '<option value="">Billing Event</option>';
            // Billing Event
            // $.each(billingEvents, function(index, value) {

            //     options += `
            //         <option value="${value}">
            //              ${beautifyLabel(value)}
            //         </option>
            //     `;

            // });
            // $('#billing_event').html(options);

            // Optimization Goal code
            // var optimizationGaols = data['optimizationGoals'];
            // let goalOptions = '<option value="">Optimization Goal</option>';

            // $.each(optimizationGaols, function(index, value) {

            //     goalOptions += `
            //         <option value="${value}">
            //             ${beautifyLabel(value)}
            //         </option>
            //     `;

            // });
            //$('#optimization_goal').html(goalOptions);

            // destination Type
            // var destinationTypes = data['destinationTypes'];
            // let destinationTypeOptions = '<option value="">Destination Type</option>';

            // $.each(destinationTypes, function(index, value) {

            //     destinationTypeOptions += `
            //         <option value="${value}">
            //             ${beautifyLabel(value)}
            //         </option>
            //     `;
            // });
            // $('#destination_type').html(destinationTypeOptions);

            // CTA
            // var ctas = data['ctas'];
            // let ctaOptions = '<option value="">Call To Action</option>';

            // $.each(ctas, function(index, value) {

            //     ctaOptions += `
            //         <option value="${value}">
            //             ${beautifyLabel(value)}
            //         </option>
            //     `;

            // });

            // $('#call_to_action').html(ctaOptions);

      
        }

        function beautifyLabel(value) {
            return value
                .toLowerCase()
                .replace(/_/g, ' ')
                .replace(/\b\w/g, char => char.toUpperCase());
        }

        function calculateBudget() {
            let budgetMode = document.getElementById('budget_mode').value;
            let budget = parseFloat(document.getElementById('budget').value) || 0;
            let startDate = document.getElementById('start_time').value;
            let endDate = document.getElementById('end_time').value;
            let allocatedBudget = budget;
            // Daily Budget Calculation
            if (budgetMode === 'daily' && startDate && endDate) {
                let start = new Date(startDate);
                let end = new Date(endDate);
                let difference = end - start;
                let days = Math.ceil(difference / (1000 * 60 * 60 * 24)) + 1;
                if (days > 0) {
                    allocatedBudget = budget * days;
                } else {
                    allocatedBudget = 0;
                }
            }
            let vat = allocatedBudget * 0.15;
            let total = allocatedBudget + vat;
            document.getElementById('budget_amount').innerText = allocatedBudget.toFixed(2);
            document.getElementById('vat_amount').innerText = vat.toFixed(2);
            document.getElementById('total_budget').innerText = total.toFixed(2);
            document.getElementById('final_budget').value = total.toFixed(2);
        }
        // Events
        document.getElementById('budget').addEventListener('input', calculateBudget);
        document.getElementById('budget_mode').addEventListener('change', calculateBudget);
        document.getElementById('start_time').addEventListener('change', calculateBudget);
        document.getElementById('end_time').addEventListener('change', calculateBudget);
    </script>

    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
