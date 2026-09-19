{{--
    Shared by create.blade.php and edit.blade.php so both stay in sync.
    Everything the header row's Save as Draft / Save Template buttons
    submit lives inside the <form> the caller opens around this include -
    see create.blade.php/edit.blade.php for why the whole page (not just
    this content block) had to move inside the <form> tag for that to work.
--}}
@php
    $isExisting = isset($template) && $template?->exists;
    $currentStatus = old('status', $template->status ?? 'draft');
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label class="form-label">Template Name *</label>
        <div class="position-relative">
            <input type="text" name="name" id="templateName" class="form-control" maxlength="100" value="{{ old('name', $template->name ?? '') }}" required>
            <span class="char-counter" id="nameCounter">0/100</span>
        </div>
        @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Subject *</label>
        <div class="position-relative">
            <input type="text" name="subject" id="templateSubject" class="form-control" maxlength="100" value="{{ old('subject', $template->subject ?? '') }}" required>
            <span class="char-counter" id="subjectCounter">0/100</span>
        </div>
        @error('subject')<p class="text-danger small">{{ $message }}</p>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Category</label>
        <input type="text" name="category" class="form-control" list="categoryOptions" value="{{ old('category', $template->category ?? '') }}" placeholder="e.g. Newsletter, Promo">
        <datalist id="categoryOptions">
            @foreach ($categories ?? [] as $category)
                <option value="{{ $category }}"></option>
            @endforeach
        </datalist>
    </div>
</div>

<div class="ai-banner mb-3">
    <div class="d-flex align-items-start gap-3 flex-wrap">
        <div class="ai-banner-icon"><i class="bx bx-sparkle"></i></div>
        <div class="flex-grow-1">
            <strong>Create with AI</strong>
            <p class="mb-2 dash-subtitle small">Describe what this email is about and AI will draft a subject and body for you.</p>
            <div class="d-flex gap-2 flex-wrap">
                <input type="text" id="aiPrompt" class="form-control" style="max-width:420px;" placeholder="e.g. Welcome email for new customers">
                <button type="button" id="generateAiBtn" class="dash-btn dash-btn-primary"><i class="bx bx-bulb"></i> Generate with AI</button>
                <div class="dropdown">
                    <button type="button" class="dash-btn dash-btn-ghost dropdown-toggle" data-bs-toggle="dropdown">Show Examples</button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item ai-example" href="javascript:;">Welcome email for new customers</a>
                        <a class="dropdown-item ai-example" href="javascript:;">Flash sale announcement - 24 hours only</a>
                        <a class="dropdown-item ai-example" href="javascript:;">Monthly newsletter roundup</a>
                        <a class="dropdown-item ai-example" href="javascript:;">Abandoned cart reminder</a>
                    </div>
                </div>
            </div>
            <div id="aiStatus" class="small mt-2" style="display:none;"></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="dash-card">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tplDesign" type="button">Design</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tplContent" type="button">Content</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tplSettings" type="button">Settings</button></li>
            </ul>

            <div class="tab-content">
                {{-- DESIGN --}}
                <div class="tab-pane fade show active" id="tplDesign">
                    <label class="form-label">Email Content</label>

                    <div class="editor-toolbar">
                        <select id="formatBlockSelect" class="form-control" style="max-width:130px;">
                            <option value="p">Paragraph</option>
                            <option value="h1">Heading 1</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                        </select>
                        <span class="toolbar-sep"></span>
                        <button type="button" class="toolbar-btn" data-cmd="bold" title="Bold"><i class="bx bx-bold"></i></button>
                        <button type="button" class="toolbar-btn" data-cmd="italic" title="Italic"><i class="bx bx-italic"></i></button>
                        <button type="button" class="toolbar-btn" data-cmd="underline" title="Underline"><i class="bx bx-underline"></i></button>
                        <button type="button" class="toolbar-btn" data-cmd="insertUnorderedList" title="Bullet list"><i class="bx bx-list-ul"></i></button>
                        <button type="button" class="toolbar-btn" data-cmd="insertOrderedList" title="Numbered list"><i class="bx bx-list-ol"></i></button>
                        <span class="toolbar-sep"></span>
                        <button type="button" class="toolbar-btn" id="insertLinkBtn" title="Insert link"><i class="bx bx-link"></i></button>
                        <button type="button" class="toolbar-btn" id="insertImageBtn" title="Insert image"><i class="bx bx-image"></i></button>
                        <span class="toolbar-sep"></span>
                        <button type="button" class="toolbar-btn" data-cmd="undo" title="Undo"><i class="bx bx-undo"></i></button>
                        <button type="button" class="toolbar-btn" data-cmd="redo" title="Redo"><i class="bx bx-redo"></i></button>
                    </div>

                    <div id="templateCanvas" class="editor-canvas" contenteditable="true">{!! old('body', $template->body ?? '<p>Hi {{first_name}},</p><p>Write your message here...</p>') !!}</div>
                    <input type="file" id="mediaFileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                    <input type="file" id="videoFileInput" accept="video/mp4,video/quicktime,video/webm,video/x-msvideo" style="display:none;">

                    <p class="dash-subtitle small mt-2 mb-0">
                        Personalization tags (substituted by SendGrid against each contact's synced fields when this template is used in a campaign):
                        <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="@{{first_name}}">first_name</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="@{{last_name}}">last_name</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="@{{email}}">email</button>
                    </p>
                </div>

                {{-- CONTENT (raw HTML source) --}}
                <div class="tab-pane fade" id="tplContent">
                    <label class="form-label">HTML Source</label>
                    <p class="dash-subtitle small mb-2">Edit the raw HTML directly - stays in sync with the Design tab's visual canvas.</p>
                    <textarea id="templateSource" class="form-control" rows="16" style="font-family:monospace;font-size:.8125rem;"></textarea>
                </div>

                {{-- SETTINGS --}}
                <div class="tab-pane fade" id="tplSettings">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mini-stat">
                                <div class="mini-stat-label"><i class="bx bx-info-circle"></i> Status</div>
                                <div class="mini-stat-value" style="font-size:1rem;text-transform:capitalize;">{{ $isExisting ? $currentStatus : 'Not saved yet' }}</div>
                                <div class="mini-stat-foot">Set via the Save as Draft / Save Template buttons above.</div>
                            </div>
                        </div>
                        @if ($isExisting)
                            <div class="col-md-3">
                                <div class="mini-stat">
                                    <div class="mini-stat-label"><i class="bx bx-calendar"></i> Created</div>
                                    <div class="mini-stat-value" style="font-size:1rem;">{{ $template->created_at->format('M j, Y') }}</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mini-stat">
                                    <div class="mini-stat-label"><i class="bx bx-history"></i> Version</div>
                                    <div class="mini-stat-value" style="font-size:1rem;">v{{ $template->current_version }}</div>
                                    <div class="mini-stat-foot">See Version History below to restore an older one.</div>
                                </div>
                            </div>
                        @else
                            <div class="col-md-6">
                                <p class="dash-subtitle small mb-0">Version history and creation date become available once this template is saved.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="dash-card mb-3">
            <h6 class="mb-2" style="color:var(--dash-heading);">Blocks</h6>
            <div class="block-grid">
                <button type="button" class="block-btn" data-block="text"><i class="bx bx-text"></i> Text</button>
                <button type="button" class="block-btn" data-block="image"><i class="bx bx-image-alt"></i> Image</button>
                <button type="button" class="block-btn" data-block="button"><i class="bx bx-rectangle"></i> Button</button>
                <button type="button" class="block-btn" data-block="divider"><i class="bx bx-minus"></i> Divider</button>
                <button type="button" class="block-btn" data-block="social"><i class="bx bx-share-alt"></i> Social Icons</button>
                <button type="button" class="block-btn" data-block="video"><i class="bx bx-play-circle"></i> Video</button>
                <button type="button" class="block-btn" data-block="header"><i class="bx bx-heading"></i> Header</button>
                <button type="button" class="block-btn" data-block="footer"><i class="bx bx-dock-bottom"></i> Footer</button>
                <button type="button" class="block-btn" data-block="spacer"><i class="bx bx-move-vertical"></i> Spacer</button>
            </div>

            <div class="ai-assist-card mt-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong style="font-size:.8125rem;"><i class="bx bx-bulb"></i> AI Content Assistant</strong>
                    <span class="dash-badge dash-badge-info">BETA</span>
                </div>
                <p class="dash-subtitle small mb-2">Need help writing the perfect message? Use AI to generate professional content for your email.</p>
                <button type="button" class="dash-btn dash-btn-ghost w-100 justify-content-center" id="scrollToAiBtn">Generate Content <i class="bx bx-right-arrow-alt"></i></button>
            </div>
        </div>

        <div class="dash-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0" style="color:var(--dash-heading);">Live Preview</h6>
                <div class="device-toggle">
                    <button type="button" class="device-btn active" data-width="100%" title="Desktop"><i class="bx bx-desktop"></i></button>
                    <button type="button" class="device-btn" data-width="375px" title="Mobile"><i class="bx bx-mobile"></i></button>
                    <button type="button" class="device-btn" data-width="360px" title="Android"><i class="bx bxl-android"></i></button>
                </div>
            </div>
            <div class="browser-chrome">
                <div class="browser-dots"><span></span><span></span><span></span></div>
            </div>
            <iframe id="templatePreview" class="template-preview-frame"></iframe>
        </div>

        <div class="dash-card" style="background:var(--dash-card-hover);box-shadow:none;">
            <h6 class="mb-2" style="color:var(--dash-heading);"><i class="bx bx-envelope-open"></i> Pro Tips</h6>
            <ul class="pro-tips-list">
                <li><i class="bx bx-check"></i> Use a compelling subject line to increase open rates</li>
                <li><i class="bx bx-check"></i> Add a clear call-to-action button</li>
                <li><i class="bx bx-check"></i> Keep your content short and focused</li>
                <li><i class="bx bx-check"></i> Use social icons to grow your audience</li>
            </ul>
        </div>
    </div>
</div>

<input type="hidden" name="body" id="templateBody">

<div class="modal fade" id="videoBlockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insert Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="dash-subtitle small">Video can't be embedded directly in an email - this inserts a clickable thumbnail that opens your video wherever it's hosted.</p>

                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="videoSource" id="videoSourceUpload" checked>
                    <label class="btn btn-outline-primary btn-sm" for="videoSourceUpload">Upload Video File</label>
                    <input type="radio" class="btn-check" name="videoSource" id="videoSourceLink">
                    <label class="btn btn-outline-primary btn-sm" for="videoSourceLink">Link to External Video</label>
                </div>

                <div id="videoSourceUploadPane" class="mb-3">
                    <label class="form-label small d-block">Video File *</label>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="videoFilePickBtn"><i class="bx bx-upload"></i> Choose Video</button>
                    <span id="videoFileName" class="dash-subtitle small ms-2">No file selected</span>
                    <div class="dash-subtitle small mt-1">MP4, MOV, WebM or AVI, up to 50MB.</div>
                    <div class="progress mt-2" id="videoUploadProgressWrap" style="height:6px;display:none;">
                        <div class="progress-bar" id="videoUploadProgressBar" style="width:0%;"></div>
                    </div>
                </div>

                <div id="videoSourceLinkPane" class="mb-3" style="display:none;">
                    <label class="form-label small">Video URL *</label>
                    <input type="url" id="videoUrlInput" class="form-control" placeholder="https://youtube.com/watch?v=...">
                </div>

                <div class="mb-2">
                    <label class="form-label small d-block">Thumbnail Image *</label>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="videoThumbPickBtn"><i class="bx bx-upload"></i> Choose Image</button>
                    <span id="videoThumbFileName" class="dash-subtitle small ms-2">No file selected</span>
                    <div><img id="videoThumbPreview" src="" alt="" style="display:none;max-width:100%;margin-top:.6rem;border-radius:8px;"></div>
                </div>
                <div id="videoBlockError" class="text-danger small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="videoBlockInsertBtn">Insert Video Block</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="headerBlockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insert Header</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">Company / Brand Name *</label>
                    <input type="text" id="headerNameInput" class="form-control" placeholder="Your Company">
                </div>
                <div class="mb-2">
                    <label class="form-label small d-block">Logo Image (optional)</label>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="headerLogoPickBtn"><i class="bx bx-upload"></i> Choose Logo</button>
                    <span id="headerLogoFileName" class="dash-subtitle small ms-2">No file selected</span>
                    <div><img id="headerLogoPreview" src="" alt="" style="display:none;max-height:48px;margin-top:.6rem;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="headerBlockInsertBtn">Insert Header</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="buttonBlockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insert Button</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">Button Text *</label>
                    <input type="text" id="buttonTextInput" class="form-control" value="Click Here" maxlength="60">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Button Link *</label>
                    <input type="url" id="buttonUrlInput" class="form-control" placeholder="https://yourdomain.com/offer">
                </div>
                <div id="buttonBlockError" class="text-danger small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="buttonBlockInsertBtn">Insert Button</button>
            </div>
        </div>
    </div>
</div>

@php
    // icon: the real, self-hosted PNG this app ships at
    // public/assets/img/icons/social/{platform}.png - referenced via
    // asset() so it's a real, absolute, publicly reachable URL both in
    // the Live Preview iframe and in an actually-sent email (an icon
    // FONT glyph like the old bx bxl-facebook-circle class renders as
    // nothing in both cases: the iframe's srcdoc document never loads
    // this app's boxicons stylesheet, and mainstream mail clients strip
    // custom @font-face/icon fonts entirely - only a plain <img> works
    // reliably in both places).
    $socialPlatforms = [
        'facebook'  => ['label' => 'Facebook', 'color' => '#1877f2'],
        'instagram' => ['label' => 'Instagram', 'color' => '#e1306c'],
        'x'         => ['label' => 'X / Twitter', 'color' => '#000000'],
        'linkedin'  => ['label' => 'LinkedIn', 'color' => '#0a66c2'],
        'tiktok'    => ['label' => 'TikTok', 'color' => '#000000'],
        'pinterest' => ['label' => 'Pinterest', 'color' => '#e60023'],
    ];
@endphp

<div class="modal fade" id="socialBlockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Insert Social Icons</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="dash-subtitle small">Pick one of your connected pages, or paste a link by hand - platforms left blank are skipped.</p>

                @foreach ($socialPlatforms as $platform => $cfg)
                    <div class="social-platform-row mb-3" data-platform="{{ $platform }}">
                        <label class="form-label small d-flex align-items-center gap-1">
                            <img src="{{ asset('assets/img/icons/social/' . $platform . '.png') }}" alt="" style="width:16px;height:16px;">
                            {{ $cfg['label'] }}
                        </label>

                        <div class="social-account-chips d-flex flex-wrap gap-2 mb-2">
                            @foreach (($socialAccountsByPlatform[$platform] ?? []) as $account)
                                <button type="button" class="social-account-chip" data-url="{{ $account['url'] }}">
                                    @if ($account['avatar_url'])
                                        <img src="{{ $account['avatar_url'] }}" alt="">
                                    @else
                                        <span class="social-account-chip-fallback"><i class="bx bx-user"></i></span>
                                    @endif
                                    <span>{{ $account['name'] }}</span>
                                </button>
                            @endforeach
                            <button type="button" class="social-account-chip is-custom" data-url="">
                                <i class="bx bx-link"></i><span>Custom Link</span>
                            </button>
                        </div>

                        <input type="url" class="form-control form-control-sm social-url-input" placeholder="https://{{ $platform }}.com/yourpage" {{ !empty($socialAccountsByPlatform[$platform]) ? 'style=display:none;' : '' }}>
                    </div>
                @endforeach

                <div id="socialBlockError" class="text-danger small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="socialBlockInsertBtn">Insert Social Icons</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.addEventListener('load', function () {
        const canvas = document.getElementById('templateCanvas');
        const source = document.getElementById('templateSource');
        const bodyInput = document.getElementById('templateBody');
        const preview = document.getElementById('templatePreview');
        const nameInput = document.getElementById('templateName');
        const subjectInput = document.getElementById('templateSubject');
        const nameCounter = document.getElementById('nameCounter');
        const subjectCounter = document.getElementById('subjectCounter');

        function updateCounter(input, counter) {
            counter.textContent = input.value.length + '/' + input.maxLength;
        }
        updateCounter(nameInput, nameCounter);
        updateCounter(subjectInput, subjectCounter);
        nameInput.addEventListener('input', () => updateCounter(nameInput, nameCounter));
        subjectInput.addEventListener('input', () => updateCounter(subjectInput, subjectCounter));

        function renderPreview() {
            preview.srcdoc = canvas.innerHTML;
            bodyInput.value = canvas.innerHTML;
        }

        // Design canvas -> hidden body input + iframe preview.
        canvas.addEventListener('input', renderPreview);
        source.value = canvas.innerHTML;
        renderPreview();

        // Content tab (raw HTML source) stays in sync with the Design
        // canvas both ways - editing one updates the other next time its
        // tab becomes active, rather than fighting over live keystrokes.
        document.querySelector('[data-bs-target="#tplContent"]').addEventListener('shown.bs.tab', function () {
            source.value = canvas.innerHTML;
        });
        document.querySelector('[data-bs-target="#tplDesign"]').addEventListener('shown.bs.tab', function () {
            canvas.innerHTML = source.value;
            renderPreview();
        });
        source.addEventListener('input', function () {
            canvas.innerHTML = source.value;
            renderPreview();
        });

        // ------------------------------------------------------------
        // TOOLBAR - native browser execCommand, no editor library added.
        // ------------------------------------------------------------
        document.querySelectorAll('.toolbar-btn[data-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                canvas.focus();
                document.execCommand(btn.dataset.cmd, false, null);
                renderPreview();
            });
        });
        document.getElementById('formatBlockSelect').addEventListener('change', function () {
            canvas.focus();
            document.execCommand('formatBlock', false, this.value);
            renderPreview();
        });
        document.getElementById('insertLinkBtn').addEventListener('click', function () {
            const url = prompt('Enter a URL:');
            if (url) {
                canvas.focus();
                document.execCommand('createLink', false, url);
                renderPreview();
            }
        });
        // ------------------------------------------------------------
        // IMAGE UPLOAD - real upload to Cloudflare R2 via the app's own
        // storage disk (same one PostController already uses for social
        // media), not a URL prompt(). One hidden <input type=file> is
        // reused by every image-needing control (toolbar, Image block,
        // Header logo, Video thumbnail) rather than one per button.
        // ------------------------------------------------------------
        const mediaFileInput = document.getElementById('mediaFileInput');
        let pendingUploadCallback = null;

        function pickImage(onUploaded) {
            pendingUploadCallback = onUploaded;
            mediaFileInput.value = '';
            mediaFileInput.click();
        }

        mediaFileInput.addEventListener('change', function () {
            const file = this.files[0];
            const callback = pendingUploadCallback;
            pendingUploadCallback = null;
            if (!file || !callback) return;

            const formData = new FormData();
            formData.append('file', file);

            fetch('{{ route('admin.email.templates.uploadMedia') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(r => r.json())
                .then(function (res) {
                    if (res.success) {
                        callback(res.url);
                    } else {
                        alert(res.message || 'Failed to upload image.');
                    }
                })
                .catch(function () {
                    alert('Failed to upload image - please try again.');
                });
        });

        document.getElementById('insertImageBtn').addEventListener('click', function () {
            canvas.focus();
            pickImage(function (url) {
                document.execCommand('insertImage', false, url);
                renderPreview();
            });
        });

        document.querySelectorAll('.insert-tag').forEach(function (btn) {
            btn.addEventListener('click', function () {
                canvas.focus();
                document.execCommand('insertText', false, btn.dataset.tag);
                renderPreview();
            });
        });

        // ------------------------------------------------------------
        // BLOCKS - each button inserts real, working HTML at the end of
        // the canvas (click-to-insert, not drag-and-drop reordering).
        // ------------------------------------------------------------
        const blockSnippets = {
            text: '<p>Your text here</p>',
            divider: '<hr style="border:none;border-top:1px solid #e2e2ea;margin:20px 0;">',
            header: '<div style="text-align:center;padding:16px 0;"><strong style="font-size:20px;">Your Company</strong></div>',
            footer: '<div style="text-align:center;color:#8b8d9c;font-size:12px;padding:16px 0;">Sent by Your Company &middot; @{{email}}</div>',
            spacer: '<div style="height:24px;"></div>',
        };

        // Bootstrap modals move DOM focus away from the canvas, which
        // clears its text selection - the cursor position has to be
        // captured before a modal opens and restored right before
        // execCommand('insertHTML', ...) runs, or the block would land
        // wherever the browser's default caret ends up instead of where
        // the seller actually clicked "Video"/"Header" from.
        let savedRange = null;
        function saveCanvasSelection() {
            const sel = window.getSelection();
            savedRange = (sel.rangeCount > 0 && canvas.contains(sel.anchorNode)) ? sel.getRangeAt(0).cloneRange() : null;
        }
        function restoreCanvasSelection() {
            canvas.focus();
            if (savedRange) {
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedRange);
            }
        }

        const videoBlockModalEl = document.getElementById('videoBlockModal');
        const videoBlockModal = new bootstrap.Modal(videoBlockModalEl);
        const videoSourceUpload = document.getElementById('videoSourceUpload');
        const videoSourceLink = document.getElementById('videoSourceLink');
        const videoSourceUploadPane = document.getElementById('videoSourceUploadPane');
        const videoSourceLinkPane = document.getElementById('videoSourceLinkPane');
        const videoUrlInput = document.getElementById('videoUrlInput');
        const videoFileInput = document.getElementById('videoFileInput');
        const videoFilePickBtn = document.getElementById('videoFilePickBtn');
        const videoFileName = document.getElementById('videoFileName');
        const videoUploadProgressWrap = document.getElementById('videoUploadProgressWrap');
        const videoUploadProgressBar = document.getElementById('videoUploadProgressBar');
        const videoThumbPickBtn = document.getElementById('videoThumbPickBtn');
        const videoThumbFileName = document.getElementById('videoThumbFileName');
        const videoThumbPreview = document.getElementById('videoThumbPreview');
        const videoBlockError = document.getElementById('videoBlockError');
        let videoThumbUrl = null;
        let uploadedVideoUrl = null;
        let videoUploadInProgress = false;

        function toggleVideoSourcePane() {
            const isUpload = videoSourceUpload.checked;
            videoSourceUploadPane.style.display = isUpload ? '' : 'none';
            videoSourceLinkPane.style.display = isUpload ? 'none' : '';
        }
        videoSourceUpload.addEventListener('change', toggleVideoSourcePane);
        videoSourceLink.addEventListener('change', toggleVideoSourcePane);

        videoFilePickBtn.addEventListener('click', function () {
            videoFileInput.value = '';
            videoFileInput.click();
        });

        // XMLHttpRequest, not fetch() - fetch has no upload-progress event,
        // and a real video file can take long enough that a silent
        // "nothing is happening" button would look broken.
        videoFileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            uploadedVideoUrl = null;
            videoUploadInProgress = true;
            videoFileName.textContent = file.name;
            videoUploadProgressWrap.style.display = 'block';
            videoUploadProgressBar.style.width = '0%';
            videoBlockError.style.display = 'none';

            const formData = new FormData();
            formData.append('file', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '{{ route('admin.email.templates.uploadVideo') }}');
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    videoUploadProgressBar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
                }
            });

            xhr.addEventListener('load', function () {
                videoUploadInProgress = false;
                let res = {};
                try { res = JSON.parse(xhr.responseText); } catch (e) {}

                if (xhr.status === 200 && res.success) {
                    uploadedVideoUrl = res.url;
                    videoFileName.textContent = file.name + ' - uploaded';
                } else {
                    videoFileName.textContent = 'No file selected';
                    videoUploadProgressWrap.style.display = 'none';
                    videoBlockError.textContent = (res.message) || 'Failed to upload video - please try again.';
                    videoBlockError.style.display = 'block';
                }
            });

            xhr.addEventListener('error', function () {
                videoUploadInProgress = false;
                videoFileName.textContent = 'No file selected';
                videoUploadProgressWrap.style.display = 'none';
                videoBlockError.textContent = 'Failed to upload video - please try again.';
                videoBlockError.style.display = 'block';
            });

            xhr.send(formData);
        });

        videoThumbPickBtn.addEventListener('click', function () {
            pickImage(function (url) {
                videoThumbUrl = url;
                videoThumbFileName.textContent = 'Image uploaded';
                videoThumbPreview.src = url;
                videoThumbPreview.style.display = 'block';
            });
        });

        document.getElementById('videoBlockInsertBtn').addEventListener('click', function () {
            const isUpload = videoSourceUpload.checked;
            const videoUrl = isUpload ? uploadedVideoUrl : videoUrlInput.value.trim();

            if (isUpload && videoUploadInProgress) {
                videoBlockError.textContent = 'Please wait for the video to finish uploading.';
                videoBlockError.style.display = 'block';
                return;
            }

            if (!videoUrl || !videoThumbUrl) {
                videoBlockError.textContent = !videoUrl
                    ? (isUpload ? 'Please upload a video file.' : 'A video URL is required.')
                    : 'A thumbnail image is required.';
                videoBlockError.style.display = 'block';
                return;
            }

            // Video itself can't be embedded in an email (every
            // mainstream mail client strips <video>) - the real, correct
            // pattern is a linked thumbnail image that opens the actual
            // video elsewhere (a real uploaded file, or a real external
            // link), which is what this inserts.
            restoreCanvasSelection();
            document.execCommand('insertHTML', false,
                '<p style="text-align:center;"><a href="' + videoUrl + '" style="display:inline-block;text-decoration:none;">'
                + '<img src="' + videoThumbUrl + '" alt="Video" style="max-width:100%;border-radius:8px;">'
                + '</a></p><p style="text-align:center;"><small>Click the thumbnail above to watch - video can\'t be embedded directly in an email.</small></p>');
            renderPreview();

            videoBlockModal.hide();
            videoUrlInput.value = '';
            uploadedVideoUrl = null;
            videoFileName.textContent = 'No file selected';
            videoUploadProgressWrap.style.display = 'none';
            videoThumbUrl = null;
            videoThumbFileName.textContent = 'No file selected';
            videoThumbPreview.style.display = 'none';
            videoBlockError.style.display = 'none';
        });

        const headerBlockModalEl = document.getElementById('headerBlockModal');
        const headerBlockModal = new bootstrap.Modal(headerBlockModalEl);
        const headerNameInput = document.getElementById('headerNameInput');
        const headerLogoPickBtn = document.getElementById('headerLogoPickBtn');
        const headerLogoFileName = document.getElementById('headerLogoFileName');
        const headerLogoPreview = document.getElementById('headerLogoPreview');
        let headerLogoUrl = null;

        headerLogoPickBtn.addEventListener('click', function () {
            pickImage(function (url) {
                headerLogoUrl = url;
                headerLogoFileName.textContent = 'Image uploaded';
                headerLogoPreview.src = url;
                headerLogoPreview.style.display = 'block';
            });
        });

        document.getElementById('headerBlockInsertBtn').addEventListener('click', function () {
            const name = headerNameInput.value.trim() || 'Your Company';

            restoreCanvasSelection();
            const logoHtml = headerLogoUrl
                ? '<img src="' + headerLogoUrl + '" alt="' + name + '" style="max-height:48px;">'
                : '<strong style="font-size:20px;">' + name + '</strong>';
            document.execCommand('insertHTML', false, '<div style="text-align:center;padding:16px 0;">' + logoHtml + '</div>');
            renderPreview();

            headerBlockModal.hide();
            headerNameInput.value = '';
            headerLogoUrl = null;
            headerLogoFileName.textContent = 'No file selected';
            headerLogoPreview.style.display = 'none';
        });

        const buttonBlockModal = new bootstrap.Modal(document.getElementById('buttonBlockModal'));
        const buttonTextInput = document.getElementById('buttonTextInput');
        const buttonUrlInput = document.getElementById('buttonUrlInput');
        const buttonBlockError = document.getElementById('buttonBlockError');

        document.getElementById('buttonBlockInsertBtn').addEventListener('click', function () {
            const text = buttonTextInput.value.trim() || 'Click Here';
            const url = buttonUrlInput.value.trim();

            if (!url) {
                buttonBlockError.textContent = 'A button link is required.';
                buttonBlockError.style.display = 'block';
                return;
            }

            restoreCanvasSelection();
            document.execCommand('insertHTML', false,
                '<p style="text-align:center;"><a href="' + url + '" style="display:inline-block;background:#7c5cff;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">' + text + '</a></p>');
            renderPreview();

            buttonBlockModal.hide();
            buttonUrlInput.value = '';
            buttonTextInput.value = 'Click Here';
            buttonBlockError.style.display = 'none';
        });

        const socialBlockModal = new bootstrap.Modal(document.getElementById('socialBlockModal'));
        const socialBlockError = document.getElementById('socialBlockError');
        const socialPlatformRows = document.querySelectorAll('.social-platform-row');

        // Each platform row: clicking a real connected-account chip sets
        // that as the row's link and hides the manual input; clicking
        // "Custom Link" clears the selection and reveals the manual
        // input instead. resolveRowUrl() below reads whichever is active.
        socialPlatformRows.forEach(function (row) {
            const chips = row.querySelectorAll('.social-account-chip');
            const urlInput = row.querySelector('.social-url-input');

            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.classList.remove('is-selected'); });
                    chip.classList.add('is-selected');

                    if (chip.classList.contains('is-custom')) {
                        urlInput.style.display = '';
                        urlInput.focus();
                    } else {
                        urlInput.style.display = 'none';
                    }
                });
            });
        });

        function resolveRowUrl(row) {
            const selectedChip = row.querySelector('.social-account-chip.is-selected');
            if (selectedChip && !selectedChip.classList.contains('is-custom')) {
                return selectedChip.dataset.url;
            }
            return row.querySelector('.social-url-input').value.trim();
        }

        document.getElementById('socialBlockInsertBtn').addEventListener('click', function () {
            const icons = [];

            socialPlatformRows.forEach(function (row) {
                const url = resolveRowUrl(row);
                if (url) {
                    icons.push({ platform: row.dataset.platform, url: url });
                }
            });

            if (icons.length === 0) {
                socialBlockError.textContent = 'Add at least one social link.';
                socialBlockError.style.display = 'block';
                return;
            }

            // Real hosted <img> icons, not an icon-font glyph - a font
            // class like "bx bxl-facebook-circle" renders as nothing both
            // in the Live Preview (its iframe never loads this app's
            // boxicons stylesheet) and in an actually-sent email (mail
            // clients strip custom icon fonts entirely).
            const html = '<p style="text-align:center;">' + icons.map(function (cfg) {
                return '<a href="' + cfg.url + '" style="margin:0 6px;text-decoration:none;">'
                    + '<img src="{{ asset('assets/img/icons/social') }}/' + cfg.platform + '.png" alt="" style="width:32px;height:32px;">'
                    + '</a>';
            }).join('') + '</p>';

            restoreCanvasSelection();
            document.execCommand('insertHTML', false, html);
            renderPreview();

            socialBlockModal.hide();
            socialPlatformRows.forEach(function (row) {
                row.querySelectorAll('.social-account-chip').forEach(function (c) { c.classList.remove('is-selected'); });
                const urlInput = row.querySelector('.social-url-input');
                urlInput.value = '';
                urlInput.style.display = row.querySelector('.social-account-chip:not(.is-custom)') ? 'none' : '';
            });
            socialBlockError.style.display = 'none';
        });

        // Image/Header/Video/Button/Social blocks each need real input
        // (an uploaded file, a link, or several links) rather than a
        // static snippet, so they open a proper modal instead of
        // inserting a dead "#" href - Text/Divider/Footer/Spacer stay
        // simple immediate inserts.
        document.querySelectorAll('.block-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const block = btn.dataset.block;

                if (block === 'image') {
                    saveCanvasSelection();
                    pickImage(function (url) {
                        restoreCanvasSelection();
                        document.execCommand('insertHTML', false, '<img src="' + url + '" alt="" style="max-width:100%;">');
                        renderPreview();
                    });
                    return;
                }

                if (block === 'header') {
                    saveCanvasSelection();
                    headerBlockModal.show();
                    return;
                }

                if (block === 'video') {
                    saveCanvasSelection();
                    videoBlockModal.show();
                    return;
                }

                if (block === 'button') {
                    saveCanvasSelection();
                    buttonBlockModal.show();
                    return;
                }

                if (block === 'social') {
                    saveCanvasSelection();
                    socialBlockModal.show();
                    return;
                }

                canvas.focus();
                document.execCommand('insertHTML', false, blockSnippets[block] || '');
                renderPreview();
            });
        });

        // ------------------------------------------------------------
        // AI GENERATION - real Gemini-backed endpoint, same integration
        // pattern already used for social post captions.
        // ------------------------------------------------------------
        const aiPrompt = document.getElementById('aiPrompt');
        const aiStatus = document.getElementById('aiStatus');
        const generateBtn = document.getElementById('generateAiBtn');

        document.querySelectorAll('.ai-example').forEach(function (link) {
            link.addEventListener('click', function () {
                aiPrompt.value = this.textContent;
            });
        });

        document.getElementById('scrollToAiBtn').addEventListener('click', function () {
            aiPrompt.scrollIntoView({ behavior: 'smooth', block: 'center' });
            aiPrompt.focus();
        });

        generateBtn.addEventListener('click', function () {
            const prompt = aiPrompt.value.trim();
            if (!prompt) {
                aiPrompt.focus();
                return;
            }

            generateBtn.disabled = true;
            generateBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Generating...';
            aiStatus.style.display = 'none';

            fetch('{{ route('admin.email.templates.generateAi') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ prompt: prompt }),
            })
                .then(r => r.json())
                .then(function (res) {
                    if (res.success) {
                        subjectInput.value = res.data.subject;
                        updateCounter(subjectInput, subjectCounter);
                        canvas.innerHTML = res.data.body;
                        renderPreview();
                        aiStatus.className = 'small mt-2 text-success';
                        aiStatus.textContent = 'Generated - review and edit before saving.';
                    } else {
                        aiStatus.className = 'small mt-2 text-danger';
                        aiStatus.textContent = res.message || 'Failed to generate content.';
                    }
                    aiStatus.style.display = 'block';
                })
                .catch(function () {
                    aiStatus.className = 'small mt-2 text-danger';
                    aiStatus.textContent = 'Failed to generate content - please try again.';
                    aiStatus.style.display = 'block';
                })
                .finally(function () {
                    generateBtn.disabled = false;
                    generateBtn.innerHTML = '<i class="bx bx-bulb"></i> Generate with AI';
                });
        });

        // ------------------------------------------------------------
        // DEVICE PREVIEW TOGGLE
        // ------------------------------------------------------------
        document.querySelectorAll('.device-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.device-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                preview.style.width = btn.dataset.width;
                preview.style.margin = btn.dataset.width === '100%' ? '0' : '0 auto';
            });
        });
    });
</script>
@endpush
