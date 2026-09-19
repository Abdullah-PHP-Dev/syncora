{{--
    Shared by create.blade.php and edit.blade.php so both stay in sync.
    4-step wizard (Details -> Design -> Recipients -> Review) reusing the
    same .wizard-step show/hide mechanism as
    admin/ads/google/campaigns/create.blade.php, with the numbered-circle
    .dash-stepper header (shared partial) made clickable/navigable for
    this flow specifically.
--}}
@php
    $templatesJson = $templates->mapWithKeys(fn ($t) => [$t->id => ['subject' => $t->subject, 'body' => $t->body]]);
    $sendersJson = $senders->mapWithKeys(fn ($s) => [$s->id => ['from_name' => $s->from_name, 'from_email' => $s->from_email]]);
    $listsJson = $lists->mapWithKeys(fn ($l) => [$l->id => $l->subscribers_count]);
    $currentAudienceType = old('audience_type', $campaign->audience_type ?? 'list');
    $currentAudienceId = old('audience_id', $campaign->audience_id ?? $campaign->email_list_id ?? null);
    $isExisting = isset($campaign) && $campaign?->exists;
@endphp

@if ($senders->isEmpty())
    <div class="alert alert-warning">
        No verified sender yet - <a href="{{ route('admin.email.setup.index') }}">finish Email Marketing setup</a> before creating a campaign.
    </div>
@endif

<div class="dash-card mb-3">
    <div class="dash-stepper">
        @foreach (['Details', 'Design', 'Recipients', 'Review'] as $i => $label)
            <div class="dash-stepper-item wizard-pill {{ $i === 0 ? 'is-current' : '' }}" data-step="{{ $i + 1 }}" style="cursor:pointer;">
                <div class="dash-stepper-circle">{{ $i + 1 }}</div>
                <span class="dash-stepper-label">{{ $label }}</span>
                <span class="dash-stepper-line"></span>
            </div>
        @endforeach
    </div>
</div>

<div class="dash-card mb-3">

    {{-- STEP 1: DETAILS --}}
    <div class="wizard-step active" data-step="1">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Campaign Name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $campaign->name ?? '') }}" required>
                @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Sender *</label>
                <select name="sender_identity_id" id="senderSelect" class="form-select" required>
                    <option value="">Select a verified sender…</option>
                    @foreach ($senders as $sender)
                        <option value="{{ $sender->id }}" @selected(old('sender_identity_id', $campaign->sender_identity_id ?? null) == $sender->id)>{{ $sender->from_name }} &lt;{{ $sender->from_email }}&gt;</option>
                    @endforeach
                </select>
                <input type="hidden" name="from_name" id="fromName" value="{{ old('from_name', $campaign->from_name ?? '') }}">
                <input type="hidden" name="from_email" id="fromEmail" value="{{ old('from_email', $campaign->from_email ?? '') }}">
                @error('sender_identity_id')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Subject *</label>
                <input type="text" name="subject" id="campaignSubject" class="form-control" value="{{ old('subject', $campaign->subject ?? '') }}" required>
                @error('subject')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Preheader</label>
                <input type="text" name="preheader" class="form-control" value="{{ old('preheader', $campaign->preheader ?? '') }}" maxlength="255" placeholder="Preview text shown next to the subject line in most inboxes">
                @error('preheader')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    {{-- STEP 2: DESIGN --}}
    <div class="wizard-step" data-step="2">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Start from a Template (optional)</label>
                <select id="templateSelect" class="form-select">
                    <option value="">Blank</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" @selected(old('email_template_id', $preselectedTemplateId ?? null) == $template->id)>{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <input type="hidden" name="email_template_id" id="emailTemplateId" value="{{ old('email_template_id', $campaign->email_template_id ?? '') }}">
                <label class="form-label">Body (HTML) *</label>
                <p class="dash-subtitle small mb-2">Personalization is handled by SendGrid itself against each contact's synced fields: <code>@{{first_name}}</code>, <code>@{{last_name}}</code>, <code>@{{email}}</code>. Unsubscribe links are added automatically by SendGrid's suppression group, not this form.</p>
                <textarea name="body" id="campaignBody" class="form-control" rows="14" required>{{ old('body', $campaign->body ?? '') }}</textarea>
                @error('body')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Live Preview</label>
                <iframe id="campaignPreview" class="w-100" style="height:280px;border:1px solid var(--dash-border);border-radius:8px;"></iframe>
            </div>
        </div>
    </div>

    {{-- STEP 3: RECIPIENTS --}}
    <div class="wizard-step" data-step="3">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Audience *</label>
                <select name="audience_type" id="audienceType" class="form-select" required>
                    <option value="list" @selected($currentAudienceType === 'list')>List</option>
                    <option value="segment" @selected($currentAudienceType === 'segment')>Segment</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">&nbsp;</label>
                <select name="audience_id" id="audienceListSelect" class="form-select" @if($currentAudienceType !== 'list') style="display:none" @endif>
                    <option value="">Select a list…</option>
                    @foreach ($lists as $list)
                        <option value="{{ $list->id }}" @selected($currentAudienceType === 'list' && $currentAudienceId == $list->id)>{{ $list->name }} ({{ $list->subscribers_count }})</option>
                    @endforeach
                </select>
                <select id="audienceSegmentSelect" class="form-select" @if($currentAudienceType !== 'segment') style="display:none" @endif>
                    <option value="">Select a segment…</option>
                    @foreach ($segments as $segment)
                        <option value="{{ $segment->id }}" @selected($currentAudienceType === 'segment' && $currentAudienceId == $segment->id)>{{ $segment->name }}</option>
                    @endforeach
                </select>
                @error('audience_id')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-4">
                <div class="dash-card" style="background:var(--dash-card-hover);box-shadow:none;margin-top:1.6rem;">
                    <div class="dash-stat-label">Estimated Recipients</div>
                    <div class="dash-stat-value" id="recipientEstimateValue">—</div>
                    <div class="dash-stat-foot" id="recipientEstimateNote"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- STEP 4: REVIEW --}}
    <div class="wizard-step" data-step="4">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="review-row"><span>Campaign Name</span><span id="reviewName">—</span></div>
                <div class="review-row"><span>Subject</span><span id="reviewSubject">—</span></div>
                <div class="review-row"><span>Sender</span><span id="reviewSender">—</span></div>
                <div class="review-row"><span>Audience</span><span id="reviewAudience">—</span></div>
            </div>
            <div class="col-md-6">
                @if ($isExisting)
                    <h6 style="color:var(--dash-heading);">Pre-flight Check</h6>
                    <div id="preflightChecks" class="dash-subtitle small">Checking…</div>
                @else
                    <div class="alert alert-info small mb-0">Pre-flight validation runs automatically the moment you send or schedule below - save as a draft first if you'd like to review the full checklist before sending.</div>
                @endif
            </div>
        </div>

        <div class="dash-card mb-3" style="background:var(--dash-card-hover);box-shadow:none;">
            <label class="form-label">Schedule for later (optional)</label>
            <input type="datetime-local" name="scheduled_at" id="scheduledAt" class="form-control" style="max-width:280px" value="{{ old('scheduled_at', isset($campaign) && $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '') }}">
            @error('scheduled_at')<p class="text-danger small">{{ $message }}</p>@enderror
            <p class="dash-subtitle small mt-2 mb-0">Leave blank and use "Save Draft" or "Send Now" below.</p>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="submit" name="action" value="save_draft" class="dash-btn dash-btn-ghost">Save Draft</button>
            <button type="submit" name="action" value="schedule" class="dash-btn dash-btn-ghost" onclick="return requireScheduleTime();">Schedule</button>
            <button type="submit" name="action" value="send_now" class="dash-btn dash-btn-primary" onclick="return confirm('Send this campaign to every recipient in the selected audience right now? This cannot be undone.');">Send Now</button>
        </div>
    </div>

    <div class="wizard-nav">
        <button type="button" class="dash-btn dash-btn-ghost" id="prevStep" style="display:none">Previous</button>
        <button type="button" class="dash-btn dash-btn-primary ms-auto" id="nextStep">Next</button>
    </div>

</div>

@push('scripts')
<script>
    window.addEventListener('load', function () {
        const templates = @json($templatesJson);
        const senders = @json($sendersJson);
        const listCounts = @json($listsJson);
        const templateSelect = document.getElementById('templateSelect');
        const templateIdInput = document.getElementById('emailTemplateId');
        const subject = document.getElementById('campaignSubject');
        const body = document.getElementById('campaignBody');
        const preview = document.getElementById('campaignPreview');
        const audienceType = document.getElementById('audienceType');
        const audienceListSelect = document.getElementById('audienceListSelect');
        const audienceSegmentSelect = document.getElementById('audienceSegmentSelect');
        const senderSelect = document.getElementById('senderSelect');
        const fromName = document.getElementById('fromName');
        const fromEmail = document.getElementById('fromEmail');
        const recipientEstimateValue = document.getElementById('recipientEstimateValue');
        const recipientEstimateNote = document.getElementById('recipientEstimateNote');

        templateSelect.addEventListener('change', function () {
            const template = templates[this.value];
            templateIdInput.value = this.value || '';

            if (template) {
                subject.value = template.subject;
                body.value = template.body;
                renderPreview();
            }
        });

        function renderPreview() {
            preview.srcdoc = body.value;
        }

        body.addEventListener('input', renderPreview);
        renderPreview();

        function toggleAudienceInputs() {
            const isList = audienceType.value === 'list';
            audienceListSelect.style.display = isList ? '' : 'none';
            audienceSegmentSelect.style.display = isList ? 'none' : '';
            audienceListSelect.name = isList ? 'audience_id' : '';
            audienceSegmentSelect.name = isList ? '' : 'audience_id';
            updateRecipientEstimate();
        }

        function updateRecipientEstimate() {
            if (audienceType.value === 'list') {
                const count = listCounts[audienceListSelect.value];
                recipientEstimateValue.textContent = count !== undefined ? count : '—';
                recipientEstimateNote.textContent = count !== undefined ? 'subscribed contacts in this list' : 'Select a list';
            } else {
                recipientEstimateValue.textContent = '—';
                recipientEstimateNote.textContent = 'SendGrid determines segment size at send time';
            }
        }

        audienceType.addEventListener('change', toggleAudienceInputs);
        audienceListSelect.addEventListener('change', updateRecipientEstimate);
        toggleAudienceInputs();

        senderSelect.addEventListener('change', function () {
            const sender = senders[this.value];
            if (sender) {
                fromName.value = sender.from_name;
                fromEmail.value = sender.from_email;
            }
        });
        if (senderSelect.value) {
            senderSelect.dispatchEvent(new Event('change'));
        }
        // "Use Template" from the templates gallery lands here with a
        // preselected value already set server-side (old()/$preselectedTemplateId
        // rendered into the <option selected> above) - firing 'change' once
        // on load applies that template's real subject/body the same way
        // picking it manually would, instead of leaving the fields blank
        // until the seller re-picks it themselves.
        if (templateSelect.value) {
            templateSelect.dispatchEvent(new Event('change'));
        }

        window.requireScheduleTime = function () {
            if (!document.getElementById('scheduledAt').value) {
                alert('Pick a date/time to schedule this campaign for.');
                return false;
            }
            return true;
        };

        // ------------------------------------------------------------
        // WIZARD STEP NAVIGATION - same show/hide-by-data-step mechanism
        // as admin/ads/google/campaigns/create.blade.php's own wizard.
        // ------------------------------------------------------------
        const wizardSteps = document.querySelectorAll('.wizard-step');
        const stepPills = document.querySelectorAll('.wizard-pill');
        const totalSteps = wizardSteps.length;
        let currentStep = 1;

        function showStep(step) {
            if (step < 1 || step > totalSteps) return;

            currentStep = step;

            wizardSteps.forEach(section => {
                section.classList.toggle('active', parseInt(section.dataset.step) === step);
            });

            stepPills.forEach(pill => {
                const pillStep = parseInt(pill.dataset.step);
                pill.classList.toggle('is-current', pillStep === step);
                pill.classList.toggle('is-done', pillStep < step);
            });

            document.getElementById('prevStep').style.display = step === 1 ? 'none' : 'inline-flex';
            document.getElementById('nextStep').style.display = step === totalSteps ? 'none' : 'inline-flex';

            if (step === totalSteps) {
                populateReviewSummary();
                runPreflight();
            }
        }

        function stepIsValid(stepNumber) {
            const stepEl = document.querySelector(`.wizard-step[data-step="${stepNumber}"]`);
            const fields = stepEl.querySelectorAll('input, select, textarea');

            for (const field of fields) {
                if (field.closest('[style*="display: none"]')) continue;
                if (!field.checkValidity()) {
                    showStep(stepNumber);
                    field.reportValidity();
                    return false;
                }
            }

            return true;
        }

        document.getElementById('nextStep').addEventListener('click', function () {
            if (!stepIsValid(currentStep)) return;
            showStep(currentStep + 1);
        });

        document.getElementById('prevStep').addEventListener('click', function () {
            showStep(currentStep - 1);
        });

        stepPills.forEach(pill => {
            pill.addEventListener('click', function () {
                const target = parseInt(this.dataset.step);

                if (target > currentStep) {
                    for (let s = currentStep; s < target; s++) {
                        if (!stepIsValid(s)) return;
                    }
                }

                showStep(target);
            });
        });

        function populateReviewSummary() {
            document.getElementById('reviewName').textContent = document.querySelector('[name="name"]').value || '—';
            document.getElementById('reviewSubject').textContent = subject.value || '—';
            document.getElementById('reviewSender').textContent = senderSelect.options[senderSelect.selectedIndex]?.text || '—';
            document.getElementById('reviewAudience').textContent = audienceType.value === 'list'
                ? (audienceListSelect.options[audienceListSelect.selectedIndex]?.text || '—')
                : (audienceSegmentSelect.options[audienceSegmentSelect.selectedIndex]?.text || '—');
        }

        // Real pre-flight check against actual infrastructure state -
        // only callable once a campaign row exists (the endpoint is
        // route-model-bound to a real EmailCampaign id), so a brand new,
        // unsaved campaign shows the informational note instead of
        // fabricating a checklist for a record that doesn't exist yet.
        function runPreflight() {
            const box = document.getElementById('preflightChecks');
            if (!box) return;

            fetch('{{ $isExisting ? route('admin.email.campaigns.preflight', $campaign) : '' }}', {
                headers: { 'Accept': 'application/json' },
            })
                .then(r => r.json())
                .then(data => {
                    box.innerHTML = data.checks.map(c => `
                        <div class="preflight-check ${c.pass ? 'is-pass' : 'is-fail'}">
                            <i class="bx ${c.pass ? 'bx-check-circle' : 'bx-x-circle'}"></i> ${c.label}
                        </div>
                    `).join('');

                    document.querySelectorAll('button[name="action"][value="send_now"], button[name="action"][value="schedule"]').forEach(btn => {
                        btn.disabled = !data.ready;
                        btn.title = data.ready ? '' : 'Every pre-flight check must pass before sending or scheduling.';
                    });
                })
                .catch(() => {
                    box.innerHTML = '<span class="text-danger">Could not load pre-flight status.</span>';
                });
        }
    });
</script>
@endpush
