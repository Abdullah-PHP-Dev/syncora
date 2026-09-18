@extends('layouts.app')

@section('title', 'Create YouTube Campaign')

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
        box-shadow: 0 5px 15px rgba(255, 0, 0, .3);
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
        color: #FF0000;
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

    .youtube-preview {
        padding: 15px;
    }

    .yt-thumb {
        width: 100%;
        aspect-ratio: 16/9;
        background: #000;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .yt-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .yt-logo {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        background: #ddd;
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
                            <a href="{{ route('admin.ads.campaigns.index', ['platform' => 'youtube']) }}">
                                <button class="btn btn-primary btn-sm">
                                    <i class="bx bx-list-ul"></i> {{ __('admin.marketing_tools.ads.campaign.header') }}
                                </button>
                            </a>
                        </div>

                        <div class="campaign-builder">
                            <div class="builder-header">
                                <div class="social-icon-mini youtube">
                                    <i class="bx bxl-youtube"></i>
                                </div>
                                <h2>Create YouTube Campaign (Demand Gen)</h2>
                                <p class="text-muted">Classic YouTube Video campaigns can no longer be created via the API - this builds a Demand Gen campaign scoped to YouTube in-feed, in-stream and Shorts placements.</p>
                                <div class="campaign-steps">
                                    <div class="step active" data-step="1">Campaign</div>
                                    <div class="step" data-step="2">Budget & Bid</div>
                                    <div class="step" data-step="3">Video</div>
                                    <div class="step" data-step="4">Ad Copy</div>
                                    <div class="step" data-step="5">Audience</div>
                                    <div class="step" data-step="6">Review</div>
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
                                                    <div class="col-md-8">
                                                        <label>Campaign Name *</label>
                                                        <input type="text" name="name" id="name" class="form-control" required maxlength="255">
                                                        <p class="error-message error-name"></p>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label>Advertising Channel Type *</label>
                                                        <select name="advertising_channel_type" id="advertising_channel_type" class="form-control" required>
                                                            <option value="DEMAND_GEN">Demand Gen</option>
                                                        </select>
                                                        <small class="form-text text-muted" id="channelTypeHint">Demand Gen video ad - YouTube surfaces only (in-feed, in-stream, Shorts).</small>
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
                                                            <span class="input-group-text">{{ $account->metadata['currency'] ?? 'USD' }}</span>
                                                            <input class="form-control" name="budget" id="budget" type="number" step="0.01" min="1" required>
                                                        </div>
                                                        <p class="error-message error-budget"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-6">
                                                        <label>Bid Strategy *</label>
                                                        <select name="bid_strategy" id="bid_strategy" class="form-control" required>
                                                            <option value="MAXIMIZE_CONVERSIONS">Maximize Conversions</option>
                                                            <option value="TARGET_CPA">Target CPA</option>
                                                            <option value="MAXIMIZE_CONVERSION_VALUE">Maximize Conversion Value</option>
                                                            <option value="TARGET_CPC">Target CPC</option>
                                                        </select>
                                                        <p class="error-message error-bid_strategy"></p>
                                                    </div>
                                                    <div class="col-md-6 bid_amount_group" style="display:none">
                                                        <label id="bidAmountLabel">Target CPA</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">{{ $account->metadata['currency'] ?? 'USD' }}</span>
                                                            <input class="form-control" name="bid_amount" id="bid_amount" type="number" step="0.01" min="0.01">
                                                        </div>
                                                        <p class="error-message error-bid_amount"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="3">
                                            <div class="builder-card">
                                                <h5>Video Creative</h5>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <label>YouTube Video ID or URL *</label>
                                                        <input type="text" name="video_id" id="video_id" class="form-control" required placeholder="https://www.youtube.com/watch?v=xxxxxxxxxxx or the bare video ID">
                                                        <p class="error-message error-video_id"></p>
                                                    </div>
                                                </div>
                                                <br>
                                                <div class="upload-zone">
                                                    <i class="bx bx-cloud-upload"></i>
                                                    <h6>Logo / Companion Image *</h6>
                                                    <p>Square logo shown alongside the video ad (max 5MB).</p>
                                                    <input type="file" name="media[]" id="mediaInput" hidden accept="image/*">
                                                    <button type="button" class="btn btn-primary" onclick="document.getElementById('mediaInput').click()">Upload Logo</button>
                                                    <p class="error-message error-media"></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="4">
                                            <div class="builder-card">
                                                <h5>Ad Copy (Demand Gen Video Responsive Ad)</h5>
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <label>Business Name *</label>
                                                        <input type="text" name="business_name" id="business_name" class="form-control" required maxlength="25">
                                                        <p class="error-message error-business_name"></p>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label>Call To Action *</label>
                                                        <select name="call_to_action" id="call_to_action" class="form-control" required>
                                                            <option value="LEARN_MORE">Learn More</option>
                                                            <option value="SHOP_NOW">Shop Now</option>
                                                            <option value="SIGN_UP">Sign Up</option>
                                                            <option value="DOWNLOAD">Download</option>
                                                            <option value="SUBSCRIBE">Subscribe</option>
                                                            <option value="BOOK_NOW">Book Now</option>
                                                            <option value="CONTACT_US">Contact Us</option>
                                                            <option value="APPLY_NOW">Apply Now</option>
                                                            <option value="GET_QUOTE">Get Quote</option>
                                                            <option value="ORDER_NOW">Order Now</option>
                                                            <option value="SEE_MORE">See More</option>
                                                            <option value="WATCH_NOW">Watch Now</option>
                                                        </select>
                                                        <p class="error-message error-call_to_action"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Final URL *</label>
                                                        <input type="url" name="target_link" id="target_link" class="form-control" required placeholder="https://example.com">
                                                        <p class="error-message error-target_link"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Headlines * (one per line, 1-5 headlines, max 40 characters each - include at least one 30 characters or shorter for placements with less room)</label>
                                                        <textarea name="headlines" id="headlines" rows="4" class="form-control" required placeholder="Discover the New Collection&#10;Shop Today, Save Big"></textarea>
                                                        <small class="form-text text-muted" id="headlinesCount"></small>
                                                        <p class="error-message error-headlines"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Long Headlines (one per line, 1-5, max 90 characters each - shown on placements with more room; leave blank to reuse the headlines above)</label>
                                                        <textarea name="long_headlines" id="long_headlines" rows="4" class="form-control" placeholder="Discover Our Full New Collection, Now Online"></textarea>
                                                        <small class="form-text text-muted" id="longHeadlinesCount"></small>
                                                        <p class="error-message error-long_headlines"></p>
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <label>Descriptions * (one per line, 1-5 descriptions, max 90 characters each)</label>
                                                        <textarea name="descriptions" id="descriptions" rows="4" class="form-control" required placeholder="Free shipping on all orders over $50. Shop now."></textarea>
                                                        <small class="form-text text-muted" id="descriptionsCount"></small>
                                                        <p class="error-message error-descriptions"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="wizard-step" data-step="5">
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
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_18_24" id="age_18_24">
                                                                <label class="form-check-label" for="age_18_24">18 – 24</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_25_34" id="age_25_34">
                                                                <label class="form-check-label" for="age_25_34">25 – 34</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_35_44" id="age_35_44">
                                                                <label class="form-check-label" for="age_35_44">35 – 44</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_45_54" id="age_45_54">
                                                                <label class="form-check-label" for="age_45_54">45 – 54</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_55_64" id="age_55_64">
                                                                <label class="form-check-label" for="age_55_64">55 – 64</label>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch" type="checkbox" name="age_range[]" value="AGE_RANGE_65_UP" id="age_65_up">
                                                                <label class="form-check-label" for="age_65_up">65+</label>
                                                            </div>
                                                        </div>
                                                        <p class="error-message error-age_range"></p>
                                                    </div>
                                                    <div class="col-md-4">
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

                                        <div class="wizard-step" data-step="6">
                                            <div class="builder-card">
                                                <h5>Review</h5>
                                                <p class="text-muted">Please review your campaign details before launching. This launches as PAUSED - activate it from the campaign list once ready.</p>
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
                                        <div class="preview-header">YouTube Ad Preview</div>
                                        <div class="youtube-preview">
                                            <div class="yt-thumb"><img id="previewThumb" style="display:none"><span id="previewThumbPlaceholder">Video thumbnail</span></div>
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <img class="yt-logo" id="previewLogo">
                                                <strong id="previewBusinessName">Business Name</strong>
                                            </div>
                                            <h6 id="previewHeadline">Your Headline Here</h6>
                                            <p id="previewDescription" class="text-muted">Your ad description will appear here as you type.</p>
                                            <a id="previewCTA" href="#" target="_blank" class="btn btn-primary w-100">Learn More</a>
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

        const channelTypeSelect = document.getElementById('advertising_channel_type');
        const bidStrategySelect = document.getElementById('bid_strategy');
        const bidAmountGroup = document.querySelector('.bid_amount_group');

        // Mirrors YoutubeAdService::biddingStrategyPayload()'s match arms
        // exactly - only DEMAND_GEN is buildable by this form today, but
        // keeping the bid-strategy list driven by channel type (rather
        // than hardcoded in the <select>) means a future channel type
        // can't silently end up offering a bidding strategy the backend
        // has no case for. manualCpc is deliberately absent - Google
        // rejects it for Demand Gen (confirmed live).
        const CHANNEL_TYPE_BID_STRATEGIES = {
            DEMAND_GEN: [
                ['MAXIMIZE_CONVERSIONS', 'Maximize Conversions'],
                ['TARGET_CPA', 'Target CPA'],
                ['MAXIMIZE_CONVERSION_VALUE', 'Maximize Conversion Value'],
                ['TARGET_CPC', 'Target CPC'],
            ],
        };

        const CHANNEL_TYPE_HINTS = {
            DEMAND_GEN: 'Demand Gen video ad - YouTube surfaces only (in-feed, in-stream, Shorts).',
        };

        function syncBidStrategyOptions() {
            const strategies = CHANNEL_TYPE_BID_STRATEGIES[channelTypeSelect.value] || [];
            const previous = bidStrategySelect.value;

            bidStrategySelect.innerHTML = strategies
                .map(([value, label]) => `<option value="${value}">${label}</option>`)
                .join('');

            if (strategies.some(([value]) => value === previous)) {
                bidStrategySelect.value = previous;
            }

            document.getElementById('channelTypeHint').textContent =
                CHANNEL_TYPE_HINTS[channelTypeSelect.value] || '';

            toggleBidAmount();
        }

        function toggleBidAmount() {
            const needsAmount = ['TARGET_CPA', 'TARGET_CPC'].includes(bidStrategySelect.value);
            bidAmountGroup.style.display = needsAmount ? '' : 'none';
            document.getElementById('bidAmountLabel').textContent =
                bidStrategySelect.value === 'TARGET_CPC' ? 'Target CPC' : 'Target CPA';
        }

        channelTypeSelect.addEventListener('change', syncBidStrategyOptions);
        bidStrategySelect.addEventListener('change', toggleBidAmount);
        syncBidStrategyOptions();

        function beautifyLabel(value) {
            return (value || '').toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        function extractYoutubeVideoId(input) {
            input = (input || '').trim();
            if (/^[a-zA-Z0-9_-]{11}$/.test(input)) return input;
            const match = input.match(/(?:v=|youtu\.be\/|shorts\/)([a-zA-Z0-9_-]{11})/);
            return match ? match[1] : null;
        }

        // ------------------------------------------------------------------
        // LIVE PREVIEW
        // ------------------------------------------------------------------
        document.getElementById('video_id')?.addEventListener('input', function() {
            const videoId = extractYoutubeVideoId(this.value);
            const thumb = document.getElementById('previewThumb');
            const placeholder = document.getElementById('previewThumbPlaceholder');
            if (videoId) {
                thumb.src = `https://img.youtube.com/vi/${videoId}/hqdefault.jpg`;
                thumb.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                thumb.style.display = 'none';
                placeholder.style.display = 'block';
            }
        });

        document.getElementById('mediaInput')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            document.getElementById('previewLogo').src = URL.createObjectURL(file);
        });

        document.getElementById('business_name')?.addEventListener('input', function() {
            document.getElementById('previewBusinessName').innerText = this.value || 'Business Name';
        });

        function nonEmptyLines(value) {
            return value.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean);
        }

        // Lines past `max` are silently dropped server-side
        // (YoutubeAdService::storeAd() caps at Google's Demand Gen video
        // ad limits) - this just surfaces that before submit instead of
        // the count quietly shrinking with no explanation.
        function updateLineCountHint(textareaId, hintId, min, max) {
            const lines = nonEmptyLines(document.getElementById(textareaId).value);
            const hint = document.getElementById(hintId);
            const over = lines.length > max;

            hint.textContent = over
                ? `${lines.length} entered - only the first ${max} will be used.`
                : `${lines.length} of ${min}-${max}`;
            hint.classList.toggle('text-danger', over);
            hint.classList.toggle('text-muted', !over);
        }

        document.getElementById('headlines')?.addEventListener('input', function() {
            const first = nonEmptyLines(this.value)[0];
            document.getElementById('previewHeadline').innerText = first || 'Your Headline Here';
            updateLineCountHint('headlines', 'headlinesCount', 1, 5);
        });

        document.getElementById('long_headlines')?.addEventListener('input', function() {
            updateLineCountHint('long_headlines', 'longHeadlinesCount', 0, 5);
        });

        document.getElementById('descriptions')?.addEventListener('input', function() {
            const first = nonEmptyLines(this.value)[0];
            document.getElementById('previewDescription').innerText = first || 'Your ad description will appear here as you type.';
            updateLineCountHint('descriptions', 'descriptionsCount', 1, 5);
        });

        updateLineCountHint('headlines', 'headlinesCount', 1, 5);
        updateLineCountHint('long_headlines', 'longHeadlinesCount', 0, 5);
        updateLineCountHint('descriptions', 'descriptionsCount', 1, 5);

        const callToActionSelect = document.getElementById('call_to_action');
        const targetLinkInput = document.getElementById('target_link');
        const previewCTA = document.getElementById('previewCTA');

        callToActionSelect?.addEventListener('change', function() {
            previewCTA.innerText = this.options[this.selectedIndex]?.text || 'Learn More';
        });

        targetLinkInput?.addEventListener('input', function() {
            previewCTA.href = this.value.trim() || '#';
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

            let ageRanges = Array.from(document.querySelectorAll('input[name="age_range[]"]:checked'))
                .map(el => beautifyLabel(el.value.replace('AGE_RANGE_', ''))).join(', ');

            let headlineCount = nonEmptyLines(document.getElementById('headlines').value).length;
            let longHeadlineCount = nonEmptyLines(document.getElementById('long_headlines').value).length;
            let descriptionCount = nonEmptyLines(document.getElementById('descriptions').value).length;

            let html = '';
            html += reviewRow('Campaign Name', document.getElementById('name').value);
            html += reviewRow('Start Date', document.getElementById('start_time').value);
            html += reviewRow('End Date', document.getElementById('end_time').value);
            html += reviewRow('Budget Mode', beautifyLabel(document.getElementById('budget_mode').value));
            html += reviewRow('Budget', document.getElementById('budget').value);
            html += reviewRow('Bid Strategy', beautifyLabel(bidStrategySelect.value));
            html += reviewRow('Video', document.getElementById('video_id').value);
            html += reviewRow('Business Name', document.getElementById('business_name').value);
            html += reviewRow('Call To Action', beautifyLabel(callToActionSelect.value));
            html += reviewRow('Headlines', headlineCount + ' headline(s)');
            html += reviewRow('Long Headlines', longHeadlineCount > 0 ? (longHeadlineCount + ' long headline(s)') : 'Reusing headlines above');
            html += reviewRow('Descriptions', descriptionCount + ' description(s)');
            html += reviewRow('Final URL', document.getElementById('target_link').value);
            html += reviewRow('Countries', countries);
            html += reviewRow('Age Range', ageRanges);
            html += reviewRow('Gender', beautifyLabel(document.getElementById('gender').value));
            html += reviewRow('Languages', languages);

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

        // ------------------------------------------------------------------
        // GLOBAL VARIABLES FOR API SUBMISSION - kept outside the load-deferred
        // block since api.js (a separate script) reads them as globals.
        // ------------------------------------------------------------------
        var url = "{{ route('admin.ads.campaigns.store', ['platform' => 'youtube']) }}";
        var redirectUrl = "{{ route('admin.ads.campaigns.index', ['platform' => 'youtube']) }}";
        var method = 'POST';
    </script>
    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
