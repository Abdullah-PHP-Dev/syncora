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
                                <div class="meta-logo">
                                    <i class="bx bxl-facebook-circle"></i>
                                    <i class="bx bxl-instagram-alt"></i>
                                </div>
                                <h2>Create Meta Campaign</h2>
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
                                <!-- LEFT SIDE -->
                                <div class="col-lg-8">
                                    <form id="campaign">
                                        <div class="wizard-step active" data-step="1">
                                        <div class="builder-card">
                                            <h5>Campaign Information</h5>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <label>Platforms</label>
                                                    <div class="platform-group">
                                                        <div class="platform-card">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch"
                                                                    type="checkbox" id="facebook" name="facebook" checked>

                                                                <label class="form-check-label ms-2" for="facebook">
                                                                    <i class="bx bxl-facebook text-primary"></i>
                                                                    Facebook
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="platform-card">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch"
                                                                    type="checkbox" id="instagram" name="instagram">

                                                                <label class="form-check-label ms-2" for="instagram">
                                                                    <i class="bx bxl-instagram text-danger"></i>
                                                                    Instagram
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <p class="error-message error-facebook error-instagram"></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Campaign Name</label>
                                                    <input type="text" name="name" id="name"
                                                        class="form-control">
                                                    <p class="error-message error-name"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Objective</label>
                                                    <select id="objective" name="objective" class="form-control">
                                                        <option value="OUTCOME_TRAFFIC">Traffic</option>
                                                        <option value="OUTCOME_SALES">Sales</option>
                                                        <option value="OUTCOME_ENGAGEMENT">Engagement</option>
                                                        <option value="OUTCOME_AWARENESS">Awareness</option>
                                                        <option value="OUTCOME_APP_PROMOTION">App promotion</option>
                                                        <option value="OUTCOME_LEADS">Leads</option>
                                                    </select>
                                                    <p class="error-message error-objective"></p>
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="2">
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
                                                    <select class="form-control" name="budget_mode" id="budget_mode">
                                                        <option value="daily_budget">Daily Budget</option>
                                                        <option value="lifetime_budget">Lifetime Budget</option>
                                                    </select>
                                                    <p class="error-message error-budget_mode"></p>
                                                </div>


                                                <div class="col-md-4">
                                                    <label>Budget</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->currency }}</span>
                                                        <input class="form-control" name="budget" id="budget"
                                                            type="number" step="0.01">
                                                    </div>
                                                    <p class="error-message error-budget"></p>
                                                </div>


                                                <div class="col-md-4">
                                                    <label>Bid Amount</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->currency }}</span>
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
                                                                    {{ $account->currency }}
                                                                    <span id="budget_amount">0.00</span>
                                                                </strong>
                                                            </div>


                                                            <div class="d-flex justify-content-between mb-2 text-muted">
                                                                <span>VAT (15%)</span>
                                                                <strong>
                                                                    {{ $account->currency }}
                                                                    <span id="vat_amount">0.00</span>
                                                                </strong>
                                                            </div>


                                                            <hr>

                                                            <input type="hidden" name="final_budget"
                                                            id="final_budget" value="">
                                                            <div class="d-flex justify-content-between">
                                                                <h5 class="mb-0">Total Budget</h5>
                                                                <h5 class="mb-0 text-primary">
                                                                    {{ $account->currency }}
                                                                    
                                                                    <span id="total_budget">0.00</span>
                                                                </h5>
                                                            </div>


                                                        </div>
                                                    </div>

                                                </div>

                                            </div>

                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="3">
                                        <div class="builder-card">

                                            <h5>Goal Setup</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Destination Type</label>
                                                    <select name="destination_type" id="destination_type"
                                                        class="form-control">
                                                    </select>
                                                    <p class="error-message error-destination_type"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Optimization Gaol</label>
                                                    <select id="optimization_goal" name="optimization_goal"
                                                        class="form-control">
                                                    </select>
                                                    <p class="error-message error-optimization_goal"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Billing Event</label>
                                                    <select id="billing_event" name="billing_event" class="form-control">
                                                    </select>
                                                    <p class="error-message error-billing_event"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Countries</label>
                                                    <select id="countries" name="countries[]" multiple
                                                        class="form-control">
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
                                            </div>

                                            <div class="row mt-3" id="pixel_fields">
                                                <div class="col-md-6">
                                                    <label>Pixel Id</label>
                                                    <input class="form-control" name="pixel_id" id="pixel_id"
                                                        type="text" placeholder="Meta Pixel ID">
                                                    <p class="error-message error-pixel_id"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Conversion Event</label>
                                                    <select class="form-control" name="custom_event_type"
                                                        id="custom_event_type">
                                                        <option value="">Select Conversion Event</option>
                                                        <option value="PURCHASE">Purchase</option>
                                                        <option value="LEAD">Lead</option>
                                                        <option value="COMPLETE_REGISTRATION">Complete Registration
                                                        </option>
                                                        <option value="ADD_TO_CART">Add To Cart</option>
                                                        <option value="INITIATED_CHECKOUT">Initiated Checkout</option>
                                                        <option value="VIEW_CONTENT">View Content</option>
                                                        <option value="SUBSCRIBE">Subscribe</option>
                                                        <option value="CONTACT">Contact</option>
                                                        <option value="SCHEDULE">Schedule</option>
                                                    </select>
                                                    <p class="error-message error-custom_event_type"></p>
                                                </div>
                                            </div>

                                            <div class="row mt-3" id="app_promotion_fields">
                                                <div class="col-md-6">
                                                    <label>App Store URL</label>
                                                    <input class="form-control" name="object_store_url"
                                                        id="object_store_url" type="url"
                                                        placeholder="https://play.google.com/store/apps/details?id=...">
                                                    <p class="error-message error-object_store_url"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>App Id</label>
                                                    <input class="form-control" name="application_id"
                                                        id="application_id" type="text">
                                                    <p class="error-message error-application_id"></p>
                                                </div>
                                            </div>

                                        </div>
                                        </div>

                                        <div class="wizard-step" data-step="4">
                                        <div class="builder-card">

                                            <h5>Ad Creative</h5>
                                            <div class="duration-buttons">
                                                <input type="hidden" name="media_type" id="media_type" value="IMAGE">
                                                <input type="hidden" name="carousel_cards" id="carousel_cards"
                                                    value="[]">
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

                                                <p id="uploadHint">Upload image (jpg/png/gif/bmp, max 30MB). For
                                                    Carousel, select 2+ images.</p>

                                                <input type="file" name="media[]" id="mediaInput" hidden
                                                    accept="image/*,video/*">

                                                <button type="button" class="btn btn-primary"
                                                    onclick="document.getElementById('mediaInput').click()">
                                                    Upload Media
                                                </button>
                                                <p class="error-message error-media"></p>
                                            </div>

                                            <div class="mt-4" id="thumbnailField" style="display:none">
                                                <label>Video Thumbnail</label>
                                                <input type="file" name="thumbnail" id="thumbnailInput"
                                                    class="form-control" accept="image/*">
                                                <p class="error-message error-thumbnail"></p>
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
                                        </div>

                                        <div class="wizard-step" data-step="5">
                                        <div class="builder-card">
                                            <h5>Audience</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <select id="call_to_action" name="call_to_action"
                                                        class="form-control">
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
                                                    <select id="gender" name="gender" class="form-control">
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
                                                    <select id="age_from" name="age_from" class="form-control">
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
                                                    <select id="age_to" name="age_to" class="form-control">
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
                                        </div>

                                        <div class="wizard-step" data-step="6">
                                            <div class="builder-card">
                                                <h5>Review</h5>
                                                <p class="text-muted">Please review your campaign details before launching.</p>
                                                <div id="reviewSummary"></div>
                                            </div>
                                        </div>

                                        <div class="wizard-nav">
                                            <button type="button" class="btn btn-outline-primary" id="prevStep"
                                                style="display:none">
                                                Previous
                                            </button>
                                            <button type="button" class="btn btn-primary ms-auto" id="nextStep">
                                                Next
                                            </button>
                                            <button type="submit" class="btn btn-primary ms-auto" id="launchBtn"
                                                style="display:none">
                                                Launch
                                            </button>
                                        </div>
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

        // The shared layout mounts a Vue 2 root on #app with no template/
        // render option (resources/js/app.js: `new Vue({ el: '#app' })`), so
        // Vue compiles this form's server-rendered HTML as an in-DOM
        // template and re-renders it once its module script finishes
        // loading - after this ordinary inline script has already run and
        // attached listeners/cached references to the original nodes. That
        // swaps every one of them out from under us, so everything below is
        // deferred to `load`, which waits for that module script (and Vue's
        // mount) to finish first. The `var` declarations above stay outside
        // this block since api.js - a separate script - reads them as
        // globals when #campaign is submitted.
        window.addEventListener('load', function() {
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

                document.getElementById('thumbnailField').style.display =
                    creativeType === 'VIDEO' ? 'block' : 'none';

                if (creativeType === 'CAROUSEL') {

                    input.setAttribute('multiple', true);
                    input.accept = "image/*";

                    carouselItems = [];
                    currentIndex = 0;

                    document.getElementById('uploadHint').innerText =
                        'Upload 2-10 images for the carousel.';

                } else if (creativeType === 'IMAGE') {

                    input.removeAttribute('multiple');
                    input.accept = "image/*";

                    document.getElementById('uploadHint').innerText =
                        'Upload image (jpg/png/gif/bmp, max 30MB).';

                } else {

                    input.removeAttribute('multiple');
                    input.accept = "video/*";

                    document.getElementById('uploadHint').innerText =
                        'Upload video (mp4/mov, max 500MB) and a thumbnail image below.';
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
        // Wizard step navigation - Campaign / Budget / Goal / Creative / Audience / Review.
        // Each section is wrapped in a .wizard-step[data-step] and only one is
        // visible at a time; Next/Previous and the step pills all drive the
        // same showStep() so they stay in sync.
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

        // Laravel validation errors land in .error-<field> elements scattered
        // across all six wizard steps - api.js populates them then fires this
        // event. Jump to whichever step holds the first one so the message the
        // user actually needs is visible, and flag every step pill that has an
        // error so nothing gets missed if there's more than one.
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

        // Required fields (eg. pixel_id, object_store_url) can live on a step
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

            let mediaSummary = creativeType === 'CAROUSEL' ?
                `${carouselItems.length} carousel image(s)` :
                (document.getElementById('mediaInput').files.length ? '1 file selected' : 'No media selected');

            let html = '';
            html += reviewRow('Campaign Name', document.getElementById('name').value);
            html += reviewRow('Objective', beautifyLabel(document.getElementById('objective').value || ''));
            html += reviewRow('Budget Type', beautifyLabel(document.getElementById('budget_mode').value || ''));
            html += reviewRow('Total Budget', document.getElementById('total_budget').innerText);
            html += reviewRow('Start Date', document.getElementById('start_time').value);
            html += reviewRow('End Date', document.getElementById('end_time').value);
            html += reviewRow('Optimization Goal', beautifyLabel(document.getElementById('optimization_goal').value || ''));
            html += reviewRow('Countries', countries);
            html += reviewRow('Media Type', beautifyLabel(creativeType));
            html += reviewRow('Media', mediaSummary);
            html += reviewRow('Call To Action', beautifyLabel(document.getElementById('call_to_action').value || ''));
            html += reviewRow('Gender', beautifyLabel(document.getElementById('gender').value || ''));
            html += reviewRow('Age Range', (document.getElementById('age_from').value || '-') + ' - ' + (document.getElementById('age_to').value || '-'));
            html += reviewRow('Languages', languages);

            document.getElementById('reviewSummary').innerHTML = html;
        }

        showStep(1);

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

            OUTCOME_TRAFFIC: {
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
                    'THRUPLAY',
                    'CONVERSATIONS'
                ],
                billingEvents: [
                    'IMPRESSIONS'
                ],
                ctas: [
                    'SEND_MESSAGE'
                ]
            },

            OUTCOME_LEADS: {
                destinationTypes: [],
                optimizationGoals: [
                    'LEAD_GENERATION',
                    'QUALITY_LEAD',
                    'OFFSITE_CONVERSIONS'
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

            OUTCOME_APP_PROMOTION: {
                destinationTypes: [
                    'APP'
                ],
                optimizationGoals: [
                    'APP_INSTALLS',
                    'OFFSITE_CONVERSIONS'
                ],
                billingEvents: [
                    'IMPRESSIONS'
                ],
                ctas: [
                    'DOWNLOAD'
                ]
            },

            OUTCOME_SALES: {
                destinationTypes: [],
                optimizationGoals: [
                    'OFFSITE_CONVERSIONS'
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
        populateFields(objectiveMap['OUTCOME_TRAFFIC']);
        toggleObjectiveFields('OUTCOME_TRAFFIC');
        $('#objective').on('change', function() {
            let objective = $(this).val();
            populateFields(objectiveMap[objective]);
            toggleObjectiveFields(objective);
        });

        function toggleObjectiveFields(objective) {
            $('#app_promotion_fields').toggle(objective === 'OUTCOME_APP_PROMOTION');
            $('#application_id, #object_store_url').prop('required', objective === 'OUTCOME_APP_PROMOTION');
        }

        // Pixel/custom event fields are only required when OFFSITE_CONVERSIONS is
        // the chosen optimization goal (Sales, or App Promotion/Leads/Engagement
        // driven off a pixel instead of a Page/App).
        $(document).on('change', '#optimization_goal', function() {
            let needsPixel = $(this).val() === 'OFFSITE_CONVERSIONS';
            $('#pixel_fields').toggle(needsPixel);
            $('#pixel_id, #custom_event_type').prop('required', needsPixel);
        });
        $('#pixel_fields').hide();

        // Meta requires optimization_goal=CONVERSATIONS whenever destination_type is
        // Messenger/WhatsApp for Traffic and Engagement - lock the goal dropdown to
        // that single option so an invalid pairing can't be submitted.
        const messagingRestrictedObjectives = ['OUTCOME_TRAFFIC', 'OUTCOME_ENGAGEMENT'];

        $(document).on('change', '#destination_type', function() {
            let objective = $('#objective').val();
            let destinationType = $(this).val();
            let isMessaging = ['MESSENGER', 'WHATSAPP'].includes(destinationType);

            if (isMessaging && messagingRestrictedObjectives.includes(objective)) {
                $('#optimization_goal').html(
                    `<option value="CONVERSATIONS">${beautifyLabel('CONVERSATIONS')}</option>`
                );
            } else if (objectiveMap[objective]) {
                let goalOptions = '<option value="">Optimization Goal</option>';
                $.each(objectiveMap[objective].optimizationGoals, function(index, value) {
                    goalOptions += `<option value="${value}">${beautifyLabel(value)}</option>`;
                });
                $('#optimization_goal').html(goalOptions);
            }
        });

        function populateFields(data) {
            var billingEvents = data['billingEvents'];
            let options = '<option value="">Billing Event</option>';
            // Billing Event
            $.each(billingEvents, function(index, value) {

                options += `
                    <option value="${value}">
                         ${beautifyLabel(value)}
                    </option>
                `;

            });
            $('#billing_event').html(options);

            // Optimization Goal code
            var optimizationGaols = data['optimizationGoals'];
            let goalOptions = '<option value="">Optimization Goal</option>';

            $.each(optimizationGaols, function(index, value) {

                goalOptions += `
                    <option value="${value}">
                        ${beautifyLabel(value)}
                    </option>
                `;

            });
            $('#optimization_goal').html(goalOptions);

            // destination Type
            var destinationTypes = data['destinationTypes'];
            let destinationTypeOptions = '<option value="">Destination Type</option>';

            $.each(destinationTypes, function(index, value) {

                destinationTypeOptions += `
                    <option value="${value}">
                        ${beautifyLabel(value)}
                    </option>
                `;
            });
            $('#destination_type').html(destinationTypeOptions);

            // CTA
            var ctas = data['ctas'];
            let ctaOptions = '<option value="">Call To Action</option>';

            $.each(ctas, function(index, value) {

                ctaOptions += `
                    <option value="${value}">
                        ${beautifyLabel(value)}
                    </option>
                `;

            });

            $('#call_to_action').html(ctaOptions);
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
            if (budgetMode === 'daily_budget' && startDate && endDate) {
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

        // Runs on the #campaign element itself, so it fires before api.js's
        // document-delegated submit handler builds the FormData - carries each
        // carousel card's link/title/description into the actual POST body.
        document.getElementById('campaign').addEventListener('submit', function() {
            if (creativeType !== 'CAROUSEL') return;

            let mainLink = document.getElementById('targetLink').value;
            let mainDescription = document.getElementById('adDescription').value;

            let cards = carouselItems.map(item => ({
                title: item.title || '',
                description: item.description || mainDescription,
                link: item.link || mainLink,
            }));

            document.getElementById('carousel_cards').value = JSON.stringify(cards);
        });
        }); // end window.addEventListener('load', ...)
    </script>

    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
