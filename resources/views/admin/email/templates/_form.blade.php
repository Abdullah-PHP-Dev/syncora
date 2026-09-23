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
    // old('schema_json') is the raw JSON string the browser last
    // submitted - covers a validation-error round trip (eg. a malformed
    // shape rejected by ValidEmailTemplateSchema) so the editor re-opens
    // with whatever the seller last had, not silently reset.
    $oldSchemaJson = old('schema_json');
    $initialSchema = $oldSchemaJson !== null ? json_decode($oldSchemaJson, true) : ($template->schema_json ?? null);
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

<email-template-designer
    :initial-schema='@json($initialSchema)'
    initial-body="{{ old('body', $template->body ?? '') }}"
    :social-accounts='@json($socialAccountsByPlatform ?? [])'
    upload-media-url="{{ route('admin.email.templates.uploadMedia') }}"
    :is-existing="{{ $isExisting ? 'true' : 'false' }}"
    autosave-url="{{ $isExisting ? route('admin.email.templates.autosave', $template) : '' }}"
    send-test-url="{{ $isExisting ? route('admin.email.templates.sendTest', $template) : '' }}"
    subject="{{ old('subject', $template->subject ?? '') }}"
></email-template-designer>

<input type="hidden" name="schema_json" id="templateSchemaJson">
<input type="hidden" name="body" id="templateBody">

@push('scripts')
<script>
    window.addEventListener('load', function () {
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

        // ------------------------------------------------------------
        // AI GENERATION - real Gemini-backed endpoint, same integration
        // pattern already used for social post captions. Stays a plain
        // fetch call outside Vue (unchanged from before) - on success it
        // now dispatches a DOM event instead of touching a contenteditable
        // canvas directly, since the block editor owns content via its own
        // reactive schema; EmailTemplateDesigner.vue listens for this and
        // replaces the schema with the generated HTML wrapped as a single
        // Custom HTML block (the same fallback used for a template that
        // predates the block editor - see blockFactory.js's
        // wrapLegacyHtmlAsSchema()).
        // ------------------------------------------------------------
        const aiPrompt = document.getElementById('aiPrompt');
        const aiStatus = document.getElementById('aiStatus');
        const generateBtn = document.getElementById('generateAiBtn');

        document.querySelectorAll('.ai-example').forEach(function (link) {
            link.addEventListener('click', function () {
                aiPrompt.value = this.textContent;
            });
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
                        subjectInput.dispatchEvent(new Event('input'));
                        document.dispatchEvent(new CustomEvent('email-designer:load-html', { detail: res.data.body }));
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
    });
</script>
@endpush
