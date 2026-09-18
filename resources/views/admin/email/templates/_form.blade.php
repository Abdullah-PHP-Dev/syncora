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
                        <select id="formatBlockSelect" class="form-select form-select-sm" style="max-width:130px;">
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
        document.getElementById('insertImageBtn').addEventListener('click', function () {
            const url = prompt('Enter an image URL:');
            if (url) {
                canvas.focus();
                document.execCommand('insertImage', false, url);
                renderPreview();
            }
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
            image: '<img src="https://via.placeholder.com/560x200" alt="" style="max-width:100%;">',
            button: '<p><a href="#" style="display:inline-block;background:#7c5cff;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">Click Here</a></p>',
            divider: '<hr style="border:none;border-top:1px solid #e2e2ea;margin:20px 0;">',
            social: '<p style="text-align:center;">'
                + '<a href="#" style="margin:0 6px;text-decoration:none;"><i class="bx bxl-facebook-circle" style="font-size:24px;color:#1877f2;"></i></a>'
                + '<a href="#" style="margin:0 6px;text-decoration:none;"><i class="bx bxl-instagram-alt" style="font-size:24px;color:#e1306c;"></i></a>'
                + '<a href="#" style="margin:0 6px;text-decoration:none;"><i class="bx bxl-twitter" style="font-size:24px;color:#1da1f2;"></i></a>'
                + '<a href="#" style="margin:0 6px;text-decoration:none;"><i class="bx bxl-linkedin-square" style="font-size:24px;color:#0a66c2;"></i></a>'
                + '</p>',
            video: '<p style="text-align:center;"><a href="#" style="display:inline-block;position:relative;text-decoration:none;">'
                + '<img src="https://via.placeholder.com/400x225" alt="Video" style="max-width:100%;border-radius:8px;">'
                + '</a></p><p style="text-align:center;"><small>Click the thumbnail above to watch - video can\'t be embedded directly in an email.</small></p>',
            header: '<div style="text-align:center;padding:16px 0;"><strong style="font-size:20px;">Your Company</strong></div>',
            footer: '<div style="text-align:center;color:#8b8d9c;font-size:12px;padding:16px 0;">Sent by Your Company &middot; @{{email}}</div>',
            spacer: '<div style="height:24px;"></div>',
        };

        document.querySelectorAll('.block-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                canvas.focus();
                document.execCommand('insertHTML', false, blockSnippets[btn.dataset.block] || '');
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
