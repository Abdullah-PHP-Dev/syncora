{{-- Shared by create.blade.php and edit.blade.php so both stay in sync --}}
@php
    $templatesJson = $templates->mapWithKeys(fn ($t) => [$t->id => ['subject' => $t->subject, 'body' => $t->body]]);
    $sendersJson = $senders->mapWithKeys(fn ($s) => [$s->id => ['from_name' => $s->from_name, 'from_email' => $s->from_email]]);
    $currentAudienceType = old('audience_type', $campaign->audience_type ?? 'list');
    $currentAudienceId = old('audience_id', $campaign->audience_id ?? $campaign->email_list_id ?? null);
@endphp

@if ($senders->isEmpty())
    <div class="alert alert-warning">
        No verified sender yet - <a href="{{ route('admin.email.setup.index') }}">finish Email Marketing setup</a> before creating a campaign.
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Campaign Name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $campaign->name ?? '') }}" required>
                @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Audience *</label>
                <select name="audience_type" id="audienceType" class="form-select" required>
                    <option value="list" @selected($currentAudienceType === 'list')>List</option>
                    <option value="segment" @selected($currentAudienceType === 'segment')>Segment</option>
                </select>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-6">
                <label class="form-label">Start from a Template (optional)</label>
                <select id="templateSelect" class="form-select">
                    <option value="">Blank</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
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
            <div class="col-12">
                <input type="hidden" name="email_template_id" id="emailTemplateId" value="{{ old('email_template_id', $campaign->email_template_id ?? '') }}">
                <label class="form-label">Body (HTML) *</label>
                <p class="text-muted small mb-2">Personalization is handled by SendGrid itself against each contact's synced fields: <code>@{{first_name}}</code>, <code>@{{last_name}}</code>, <code>@{{email}}</code>. Unsubscribe links are added automatically by SendGrid's suppression group, not this form.</p>
                <textarea name="body" id="campaignBody" class="form-control" rows="14" required>{{ old('body', $campaign->body ?? '') }}</textarea>
                @error('body')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Live Preview</label>
                <iframe id="campaignPreview" class="w-100" style="height:280px;border:1px solid rgba(0,0,0,.1);border-radius:8px;"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <label class="form-label">Schedule for later (optional)</label>
        <input type="datetime-local" name="scheduled_at" id="scheduledAt" class="form-control" style="max-width:280px" value="{{ old('scheduled_at', isset($campaign) && $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '') }}">
        @error('scheduled_at')<p class="text-danger small">{{ $message }}</p>@enderror
        <p class="text-muted small mt-2 mb-0">Leave blank and use "Save Draft" or "Send Now" below.</p>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <button type="submit" name="action" value="save_draft" class="btn btn-outline-secondary">Save Draft</button>
        <button type="submit" name="action" value="schedule" class="btn btn-outline-primary" onclick="return requireScheduleTime();">Schedule</button>
        <button type="submit" name="action" value="send_now" class="btn btn-primary" onclick="return confirm('Send this campaign to every recipient in the selected audience right now? This cannot be undone.');">Send Now</button>
    </div>
</div>

@push('scripts')
<script>
    window.addEventListener('load', function () {
        const templates = @json($templatesJson);
        const senders = @json($sendersJson);
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
        }

        audienceType.addEventListener('change', toggleAudienceInputs);
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

        window.requireScheduleTime = function () {
            if (!document.getElementById('scheduledAt').value) {
                alert('Pick a date/time to schedule this campaign for.');
                return false;
            }
            return true;
        };
    });
</script>
@endpush
