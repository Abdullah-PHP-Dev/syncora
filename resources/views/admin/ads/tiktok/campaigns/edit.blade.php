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
                                <div class="meta-logo">
                                    <i class="bx bxl-facebook-circle"></i>
                                    <i class="bx bxl-instagram-alt"></i>
                                </div>
                                <h2>Create Meta Campaign</h2>
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
                                            @php
                                                $adGroup = $campaign->adGroups->first();
                                                $ageGroup = json_decode($adGroup->age_groups);
                                                $creative = $adGroup->creatives->first();
                                                $ad = $creative->ads->first();
                                                $media = $creative->media;
                                                $languages = json_decode($adGroup->languages);
                                                $selectedCountries = json_decode($adGroup->location_ids);
                                                // targeting_criteria holds the resolved TikTok-side IDs plus
                                                // every raw local selection needed to re-populate this form -
                                                // see TiktokAdService::buildAudienceTargeting().
                                                $targetingLocal = json_decode($adGroup->targeting_criteria ?? '{}', true)['local'] ?? [];
                                            @endphp
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Campaign Name</label>
                                                    <input type="text" name="name" id="name" value="{{ old('name', $campaign->name) }}" class="form-control">
                                                    <p class="error-message error-name"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Objective</label>
                                                    <select id="objective" name="objective" class="form-control">
                                                        @foreach ([
                                                            'APP_PROMOTION' => 'App Promotion',
                                                            'WEB_CONVERSIONS' => 'Web Conversions',
                                                            'REACH' => 'Reach',
                                                            'TRAFFIC' => 'Traffic',
                                                            'VIDEO_VIEWS' => 'Video Views',
                                                            'ENGAGEMENT' => 'Engagement',
                                                            'LEAD_GENERATION' => 'Lead Generation',
                                                        ] as $value => $label)
                                                            <option value="{{ $value }}" @selected(old('objective', $campaign->objective) == $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p class="error-message error-objective"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="builder-card">

                                            <h5>Budget & Schedule</h5>
                                            <div class="row mt-4">
                                                <div class="col-md-6">
                                                    <label>Start Date</label>
                                                    <input type="date" id="start_time" name="start_time" value="{{ \Carbon\Carbon::parse($campaign->start_time)->format('Y-m-d') }}" class="form-control">
                                                    <p class="error-message error-start_time"></p>
                                                </div>

                                                <div class="col-md-6">
                                                    <label>End Date</label>
                                                    <input type="date" id="end_time" name="end_time" class="form-control"  value="{{ \Carbon\Carbon::parse($campaign->end_time)->format('Y-m-d') }}">
                                                    <p class="error-message error-end_time"></p>
                                                </div>

                                            </div>

                                            <div class="row mt-4">

                                                <div class="col-md-4">
                                                    <label>Budget Type</label>
                                                    <select class="form-control" name="budget_mode" id="budget_mode">
                                                        <option value="daily_budget" @selected(old('budget_mode', $campaign->budget_mode) == 'daily_budget')>Daily Budget</option>
                                                        <option value="lifetime_budget" @selected(old('budget_mode', $campaign->budget_mode) == 'lifetime_budget')>Lifetime Budget</option>
                                                    </select>
                                                    <p class="error-message error-budget_mode"></p>
                                                </div>


                                                <div class="col-md-4">
                                                    <label>Budget</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->currency }}</span>
                                                        <input class="form-control" name="budget" id="budget"
                                                            type="number" step="0.01" value="{{$adGroup->budget}}">
                                                    </div>
                                                    <p class="error-message error-budget"></p>
                                                </div>


                                                <div class="col-md-4">
                                                    <label>Bid Amount</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">{{ $account->currency }}</span>
                                                        <input class="form-control" name="bid_amount" value="{{$adGroup->bid_price}}" id="bid_amount"
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
                                        <div class="builder-card">

                                            <h5>Goal Setup</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Destination Type</label>
                                                    <select name="destination_type" id="destination_type" class="form-control">
                                                    </select>
                                                    <p class="error-message error-destination_type"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Optimization Gaol</label>
                                                    <select id="optimization_goal" name="optimization_goal" class="form-control">
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
                                                            <option value="{{ $country->id }}" {{ in_array($country->code, $selectedCountries) ? 'selected' : '' }}>{{ $country->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <p class="error-message error-countries"></p>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>TikTok Identity *</label>
                                                    <select name="page_id" id="page_id" class="form-control" data-current="{{ $creative->page_id }}" required>
                                                        <option value="{{ $creative->page_id }}">-- Loading identities... --</option>
                                                    </select>
                                                    <input type="hidden" name="identity_type" id="identity_type" value="TT_USER">
                                                    <small class="text-muted">A TikTok account linked to this ad account. If none are listed, link one in TikTok Ads Manager → Assets → Identity (or via Business Center) first - this can't be created from here.</small>
                                                    <p class="error-message error-page_id"></p>
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

                                                @foreach ($media as $each)
                                                    <input type="hidden" name="old_media_id[]" value="{{$each->id}}">
                                                @endforeach

                                                <input type="file" name="media[]" id="mediaInput" value="" hidden
                                                    accept="image/*,video/*">

                                                <button type="button" class="btn btn-primary"
                                                    onclick="mediaInput.click()">
                                                    Upload Media
                                                </button>
                                                <p class="error-message error-media"></p>
                                            </div>

                                            <div class="mt-3" id="carouselMusicBlock" style="display:none;">
                                                <label>Background Music *</label>
                                                <input type="file" name="music" id="musicInput" class="form-control" accept=".mp3,.wav,.m4a,.flac">
                                                <small class="text-muted">Required for Carousel Ads - TikTok won't create the ad without one. MP3/WAV/M4A/FLAC, max 10MB. Leave blank to keep the existing track.</small>
                                                <p class="error-message error-music"></p>
                                            </div>

                                            <div class="mt-3" id="videoCoverBlock" style="display:none;">
                                                <label>Video Cover / Thumbnail</label>
                                                <input type="file" name="video_cover" id="videoCoverInput" class="form-control" accept="image/*">
                                                <small class="text-muted">Optional - only used if you also re-upload the video above. Leave blank to keep the current cover.</small>
                                                <p class="error-message error-video_cover"></p>
                                            </div>

                                            <div class="mt-4">

                                                <label>Description</label>

                                                <textarea id="adDescription" name="description" rows="4" class="form-control">{{$creative->message}}</textarea>
                                                <p class="error-message error-description"></p>
                                            </div>
                                            <div class="mt-4">
                                                <label>Target URL</label>

                                                <input type="url" name="target_link" id="targetLink" value="{{$creative->url}}" class="form-control" placeholder="https://example.com">
                                                <p class="error-message error-target_link"></p>
                                            </div>

                                        </div>
                                        <div class="builder-card">
                                            <h5>Audience</h5>
                                            @php
                                                $selectedAgeRanges = json_decode($adGroup->age_groups ?? '[]', true) ?: [];
                                            @endphp
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
                                                        <option value="GENDER_UNLIMITED" @selected(old('gender', $adGroup->gender) == 'GENDER_UNLIMITED')>All</option>
                                                        <option value="GENDER_MALE" @selected(old('gender', $adGroup->gender) == 'GENDER_MALE')>Male</option>
                                                        <option value="GENDER_FEMALE" @selected(old('gender', $adGroup->gender) == 'GENDER_FEMALE')>Female</option>
                                                    </select>
                                                    <p class="error-message error-gender"></p>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <label>Age Range</label>
                                                    <div class="checkbox-group">
                                                        @foreach (['AGE_18_24' => '18 – 24', 'AGE_25_34' => '25 – 34', 'AGE_35_44' => '35 – 44', 'AGE_45_54' => '45 – 54', 'AGE_55_100' => '55+'] as $value => $label)
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="{{ $value }}" id="age_{{ strtolower($value) }}" @checked(in_array($value, $selectedAgeRanges))>
                                                                <label class="form-check-label" for="age_{{ strtolower($value) }}">{{ $label }}</label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <p class="error-message error-age_range"></p>
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
                                                                    id="english" {{ in_array('english', $languages) ? 'checked' : '' }}>

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
                                                                    name="languages[]" value="arabic" {{ in_array('arabic', $languages) ? 'checked' : '' }}>

                                                                <label class="form-check-label ms-2" for="arabic">
                                                                    Arabic
                                                                </label>
                                                                <p class="error-message error-languages"></p>
                                                            </div>
                                                        </div>

                                                    </div>
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
                                                                    <input class="form-check-input platform-switch" type="checkbox" name="operating_systems[]" value="{{ $value }}" id="os_{{ strtolower($value) }}" @checked(in_array($value, $targetingLocal['operating_systems'] ?? []))>
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
                                                                    <input class="form-check-input platform-switch" type="checkbox" name="network_types[]" value="{{ $value }}" id="network_{{ strtolower($value) }}" @checked(in_array($value, $targetingLocal['network_types'] ?? []))>
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
                                                            <input type="number" name="device_price_min" id="device_price_min" class="form-control" min="0" value="{{ $targetingLocal['device_price_min'] ?? '' }}" placeholder="Min">
                                                            <p class="error-message error-device_price_min"></p>
                                                        </div>
                                                        <div class="col-6">
                                                            <input type="number" name="device_price_max" id="device_price_max" class="form-control" min="0" value="{{ $targetingLocal['device_price_max'] ?? '' }}" placeholder="Max">
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
                                                    <input type="text" name="interest_categories" id="interest_categories" class="form-control" value="{{ $targetingLocal['interest_categories'] ?? '' }}" placeholder="e.g. Beauty & Personal Care, Gaming">
                                                    <p class="error-message error-interest_categories"></p>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label>Additional Interest Keywords (comma-separated)</label>
                                                    <input type="text" name="interest_keywords" id="interest_keywords" class="form-control" value="{{ $targetingLocal['interest_keywords'] ?? '' }}" placeholder="e.g. skincare, home workout">
                                                    <p class="error-message error-interest_keywords"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Behaviors (comma-separated)</label>
                                                    <input type="text" name="behaviors" id="behaviors" class="form-control" value="{{ $targetingLocal['behaviors'] ?? '' }}" placeholder="e.g. Engaged with Fashion Videos">
                                                    <p class="error-message error-behaviors"></p>
                                                </div>
                                            </div>
                                            <hr class="my-4">
                                            <h6>Custom Audiences</h6>
                                            <p class="text-muted mb-1" style="font-size:0.8rem;">Paste in existing Custom Audience IDs from TikTok Ads Manager (comma-separated) - this app doesn't create new audiences, only targets ones you've already built.</p>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Include Audiences</label>
                                                    <input type="text" name="audience_ids" id="audience_ids" class="form-control" value="{{ $targetingLocal['audience_ids'] ?? '' }}" placeholder="e.g. 7123456789012345678">
                                                    <p class="error-message error-audience_ids"></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Exclude Audiences</label>
                                                    <input type="text" name="excluded_audience_ids" id="excluded_audience_ids" class="form-control" value="{{ $targetingLocal['excluded_audience_ids'] ?? '' }}" placeholder="e.g. 7123456789012345679">
                                                    <p class="error-message error-excluded_audience_ids"></p>
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
                                            <img @if ($media->first()->type === 'IMAGE') src="{{$media->first()->url}}"  style="display:block" @else  style="display:none" @endif id="previewImage" class="preview-image">
                                            <video @if ($media->first()->type === 'VIDEO') src="{{$media->first()->url}}" style="display:block;width:100%;border-radius:12px;" @else  style="display:none;width:100%;border-radius:12px;" @endif id="previewVideo" class="preview-image" controls>
                                            </video>
                                            <div id="carouselPreview" style="display:none">
                                                <img id="carouselImage" class="preview-image">
                                                <small class="text-muted d-block mt-2">TikTok Carousel Ads share one caption and CTA across every card (set below) - there's no per-image title or link.</small>
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
                                                    {{$campaign->name}}
                                                </h6>
                                                <p id="previewDescription">
                                                    {{$creative->message}}
                                                </p>
                                            </div>
                                            <a id="previewCTA" href="#" target="_blank"
                                                class="btn btn-primary w-100">
                                                {{ ucwords(strtolower(str_replace('_', ' ', $ad->call_to_action))) }}
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
        var campaignId = @json($campaign->id);
        var areYouSure = "{{ __('admin.sweet-alert.are-you-sure') }}";
        var selectedObjective = "{{ $campaign->objective }}";
        var selectedDestinationType = "{{ $adGroup->destination_type }}";
        var selectedOptimizationGoal = "{{ $adGroup->optimization_goal }}";
        var selectedBillingEvent = "{{ $adGroup->billing_event }}";
        var selectedCTA = "{{ $ad->call_to_action }}";
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
        var url = "{{ route('admin.ads.campaigns.update', ['platform' => 'tiktok', 'campaign' => '__ID__']) }}";
        url = url.replace('__ID__', campaignId);
        var destroyAPIUrl = "{{ route('admin.apis.destroy', ['api' => ':API']) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'tiktok']) }}";
        //  var url = "{{ route('admin.ads.campaigns.store', ['platform' => 'tiktok']) }}";
        var method = 'PUT';
        var edit = "{{ __('admin.table.edit') }}";
        var deletebutton = "{{ __('admin.table.delete') }}";
        $('#countries').select2();

        // LOAD TIKTOK IDENTITIES - replaces a free-text identity id (which
        // could silently reference an identity TikTok has since revoked
        // access to) with only the identities currently authorized for
        // this ad account, per TiktokAdService::getIdentities(). The
        // ad's existing identity is kept as an option even if it's not
        // in that list, so the field doesn't just go blank - but flagged
        // so it's clear a re-pick may be needed (this is exactly the
        // "You no longer have access..." case). Each option carries its
        // identity_type so #identity_type stays in sync - buildCreative()
        // needs the type to match the id or TikTok rejects the update.
        var identityUrl = "{{ route('admin.ads.identities', ['platform' => 'tiktok']) }}";
        var identitySelect = document.getElementById('page_id');
        var identityTypeInput = document.getElementById('identity_type');

        function renderIdentityOptions(identities) {
            if (!identitySelect) return;

            var current = identitySelect.dataset.current;
            var stillValid = identities.some(function(identity) { return identity.id === current; });

            var options = identities.map(function(identity) {
                return '<option value="' + identity.id + '" data-type="' + identity.type + '"' + (identity.id === current ? ' selected' : '') + '>' + identity.name + '</option>';
            }).join('');

            if (current && !stillValid) {
                options += '<option value="' + current + '" data-type="TT_USER" selected>' + current + ' (may no longer be authorized - please re-select)</option>';
            }

            identitySelect.innerHTML = options || '<option value="">-- No linked TikTok accounts - add one in TikTok Ads Manager --</option>';

            if (identityTypeInput) {
                var selected = identitySelect.options[identitySelect.selectedIndex];
                identityTypeInput.value = (selected && selected.dataset.type) || 'TT_USER';
            }
        }

        function loadIdentities() {
            return fetch(identityUrl)
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    renderIdentityOptions(result.success ? result.data : []);
                    return result;
                })
                .catch(function() {
                    if (identitySelect) identitySelect.innerHTML = '<option value="' + identitySelect.dataset.current + '" selected>' + identitySelect.dataset.current + ' (could not load identities)</option>';
                });
        }

        loadIdentities();

        if (identitySelect) {
            identitySelect.addEventListener('change', function() {
                var selected = this.options[this.selectedIndex];
                if (identityTypeInput) identityTypeInput.value = (selected && selected.dataset.type) || 'TT_USER';
            });
        }

        document.getElementById('name').addEventListener('keyup', function() {

            document.getElementById('previewTitle')
                .innerText = this.value || 'Campaign Name';

        });

        document.getElementById('adDescription').addEventListener('keyup', function() {

            document.getElementById('previewDescription')
                .innerText = this.value || 'Ad description';

        });

        // Reflects the ad's actual saved format instead of always
        // defaulting to IMAGE - without this, opening an existing
        // Carousel ad never set creativeType to 'CAROUSEL' unless the
        // admin manually re-clicked the Carousel button.
        let creativeType = @json($creative->type ?? 'IMAGE');
        let carouselItems = @json($media->map(fn($m) => [
            'image' => $m->url,
        ])->values());
        let currentIndex = 0;
        let mediaInput = document.getElementById('mediaInput');
        let carousel = document.getElementById('carouselPreview');

        if (creativeType === 'CAROUSEL') {
            document.querySelectorAll('.media-type').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.type === 'CAROUSEL');
            });
            document.getElementById('media_type').value = 'CAROUSEL';
            document.getElementById('carouselMusicBlock').style.display = '';
            carousel.style.display = 'block';
            loadCarouselItem();
        } else {
            carouselItems = [];
        }

        document.getElementById('videoCoverBlock').style.display = creativeType === 'VIDEO' ? '' : 'none';



        document.querySelectorAll('.media-type').forEach(btn => {
            btn.addEventListener('click', function() {

                document.querySelectorAll('.media-type')
                    .forEach(x => x.classList.remove('active'));

                this.classList.add('active');

                creativeType = this.dataset.type;

                let input = document.getElementById('mediaInput');
                $('#media_type').val(creativeType);
                console.log($('#media_type').val());
                document.getElementById('carouselMusicBlock').style.display = creativeType === 'CAROUSEL' ? '' : 'none';
                document.getElementById('videoCoverBlock').style.display = creativeType === 'VIDEO' ? '' : 'none';
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

            document.getElementById('carouselCounter').innerHTML =
                `${currentIndex + 1} / ${carouselItems.length}`;
        }

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

            OUTCOME_APP_PROMOTION: {
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
        populateFields(objectiveMap[selectedObjective]);
        $('#objective').on('change', function() {
            let objective = $(this).val();
            populateFields(objectiveMap[objective]);
        });

        function populateFields(data) {
            var billingEvents = data['billingEvents'];
            let options = '<option value="">Billing Event</option>';
            // Billing Event
            $.each(billingEvents, function(index, value) {
                options += `
                    <option value="${value}" ${selectedBillingEvent == value ? 'selected' : ''}>
                         ${beautifyLabel(value)}
                    </option>
                `;

            });
            $('#billing_event').html(options);

            // Optimization Goal code
            var optimizationGoals = data['optimizationGoals'];
            let goalOptions = '<option value="">Optimization Goal</option>';

            $.each(optimizationGoals, function(index, value) {
                goalOptions += `
                    <option value="${value}" ${selectedOptimizationGoal == value ? 'selected' : ''}>
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
                    <option value="${value}" ${selectedDestinationType == value ? 'selected' : ''} >
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
                    <option value="${value}" ${selectedCTA == value ? 'selected' : ''}>
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

        calculateBudget();
        
        function calculateBudget() {
            let budgetMode = document.getElementById('budget_mode').value ?? '{{$adGroup->budget_mode}}';
            let budget = parseFloat(document.getElementById('budget').value) || {{ $adGroup->budget ?? 0 }};
            let startDate = document.getElementById('start_time').value ?? '{{$adGroup->start_time}}';
            let endDate = document.getElementById('end_time').value ?? '{{$adGroup->end_time}}';
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
