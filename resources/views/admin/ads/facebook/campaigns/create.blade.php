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

                                        <div class="builder-card">

                                            <h5>Campaign Information</h5>

                                            <div class="row">

                                                <div class="col-md-12">
                                                    <label>Platforms</label>

                                                    <div class="platform-group">

                                                        <div class="platform-card">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch"
                                                                    type="checkbox" id="facebookPlatform" checked>

                                                                <label class="form-check-label ms-2" for="facebookPlatform">
                                                                    <i class="bx bxl-facebook text-primary"></i>
                                                                    Facebook
                                                                </label>
                                                            </div>
                                                        </div>

                                                        <div class="platform-card">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch"
                                                                    type="checkbox" id="instagramPlatform">

                                                                <label class="form-check-label ms-2"
                                                                    for="instagramPlatform">
                                                                    <i class="bx bxl-instagram text-danger"></i>
                                                                    Instagram
                                                                </label>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label>Campaign Name</label>
                                                    <input type="text" id="campaignName" class="form-control">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="builder-card">

                                            <h5>Budget & Schedule</h5>

                                            <div class="duration-buttons">

                                                <button type="button" class="duration-btn active">
                                                    1 Week
                                                </button>

                                                <button type="button" class="duration-btn">
                                                    2 Weeks
                                                </button>

                                                <button type="button" class="duration-btn">
                                                    1 Month
                                                </button>

                                                <button type="button" class="duration-btn">
                                                    Custom
                                                </button>

                                            </div>

                                            <div class="row mt-4">

                                                <div class="col-md-6">
                                                    <label>Start Date</label>
                                                    <input type="date" class="form-control">
                                                </div>

                                                <div class="col-md-6">
                                                    <label>End Date</label>
                                                    <input type="date" class="form-control">
                                                </div>

                                            </div>

                                            <div class="row mt-4">

                                                <div class="col-md-4">
                                                    <label>Budget Type</label>
                                                    <select class="form-select">
                                                        <option>Daily Budget</option>
                                                        <option>Lifetime Budget</option>
                                                    </select>
                                                </div>

                                                <div class="col-md-4">
                                                    <label>Budget</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">SAR</span>
                                                        <input class="form-control">
                                                    </div>
                                                </div>

                                                <div class="col-md-4">
                                                    <label>Bid Amount</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">SAR</span>
                                                        <input class="form-control">
                                                    </div>
                                                </div>

                                            </div>

                                        </div>
                                        <div class="builder-card">

                                            <h5>Goal Setup</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <select id="optimization_gaol" class="form-select">
                                                        <option value="">Optimization Gaol</option>
                                                        <option value="LEARN_MORE">Learn More</option>
                                                        <option value="SHOP_NOW">Shop Now</option>
                                                        <option value="SIGN_UP">Sign Up</option>
                                                        <option value="BOOK_NOW">Book Now</option>
                                                        <option value="CONTACT_US">Contact Us</option>
                                                        <option value="CALL_NOW">Call Now</option>
                                                        <option value="SEND_MESSAGE">Send Message</option>
                                                        <option value="DOWNLOAD">Download</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <select id="billing_event" class="form-select">
                                                        <option value="">Billing Event</option>
                                                        <option value="male">Male</option>
                                                        <option value="female">Female</option>
                                                        <option value="both">Both</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <select id="destination_type" class="form-select">
                                                        <option value="">Destination Type</option>
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
                                                </div>
                                            </div>

                                        </div>
                                        <div class="builder-card">

                                            <h5>Ad Creative</h5>
                                            <div class="duration-buttons">

                                                <button type="button" class="duration-btn active">
                                                    Image
                                                </button>

                                                <button type="button" class="duration-btn">
                                                    Carousel
                                                </button>

                                                <button type="button" class="duration-btn">
                                                    Video
                                                </button>

                                            </div>
                                            <br>
                                            <div class="upload-zone">
                                                <i class="bx bx-cloud-upload"></i>

                                                <h6>Drag & Drop Media</h6>

                                                <p>Upload image or video</p>

                                                <input type="file" id="mediaInput" hidden>

                                                <button type="button" class="btn btn-primary" onclick="mediaInput.click()">
                                                    Upload Media
                                                </button>
                                            </div>

                                            <div class="mt-4">

                                                <label>Description</label>

                                                <textarea id="adDescription" rows="4" class="form-control"></textarea>

                                            </div>
                                            <div class="mt-4">
                                                <label>Target URL</label>

                                                <input type="url" id="targetLink" class="form-control"
                                                    placeholder="https://example.com">
                                            </div>

                                        </div>
                                        <div class="builder-card">

                                            <h5>Audience</h5>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <select id="call_to_action" class="form-select">
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
                                                </div>
                                                <div class="col-md-6">
                                                    <select id="gender" class="form-select">
                                                        <option value="">Gender</option>
                                                        <option value="male">Male</option>
                                                        <option value="female">Female</option>
                                                        <option value="both">Both</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <br>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <select id="age_from" class="form-select">
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
                                                </div>
                                                <div class="col-md-6">
                                                    <select id="age_to" class="form-select">
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
                                                                    type="checkbox" name="langauges[]" value="english" id="facebookPlatform" checked>

                                                                <label class="form-check-label ms-2" for="facebookPlatform">
                                                                    English
                                                                </label>
                                                            </div>
                                                        </div>

                                                        <div class="platform-card">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input platform-switch"
                                                                    type="checkbox" id="instagramPlatform" name="langauges[]" value="arabic">

                                                                <label class="form-check-label ms-2"
                                                                    for="instagramPlatform">
                                                                    Arabic
                                                                </label>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                                
                                            </div>
                                        </div>
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
                                                        <strong>Socialeaz</strong>
                                                        <div>Sponsored</div>
                                                    </div>
                                                </div>

                                                <img id="previewImage" class="preview-image" style="display:none">

                                                <video id="previewVideo" class="preview-image" controls
                                                    style="display:none;width:100%;border-radius:12px;">
                                                </video>

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
        var edit = "{{ __('admin.table.edit') }}";
        var deletebutton = "{{ __('admin.table.delete') }}";
        document.getElementById('campaignName').addEventListener('keyup', function() {

            document.getElementById('previewTitle')
                .innerText = this.value || 'Campaign Name';

        });

        document.getElementById('adDescription').addEventListener('keyup', function() {

            document.getElementById('previewDescription')
                .innerText = this.value || 'Ad description';

        });

        document.getElementById('mediaInput').addEventListener('change', function(e) {

            let file = e.target.files[0];

            if (!file) return;

            let image = document.getElementById('previewImage');
            let video = document.getElementById('previewVideo');

            image.style.display = 'none';
            video.style.display = 'none';

            let fileURL = URL.createObjectURL(file);

            if (file.type.startsWith('image/')) {

                image.src = fileURL;
                image.style.display = 'block';

            } else if (file.type.startsWith('video/')) {

                video.src = fileURL;
                video.style.display = 'block';

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

        document.querySelectorAll('.platform-switch')
            .forEach(item => {

                item.addEventListener('change', updatePlatformCards);

            });

        updatePlatformCards();
    </script>

    <script src="{{ asset('assets/js/admin/api.js') }}"></script>
@endpush
