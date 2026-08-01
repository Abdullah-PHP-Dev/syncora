{{-- Shared by create.blade.php and edit.blade.php so both stay in sync --}}
@php
    $templatesJson = $templates->mapWithKeys(fn ($t) => [$t->id => ['subject' => $t->subject, 'body' => $t->body]]);
@endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Campaign Name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $campaign->name ?? '') }}" required>
                @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">List *</label>
                <select name="email_list_id" class="form-select" required>
                    <option value="">Select a list…</option>
                    @foreach ($lists as $list)
                        <option value="{{ $list->id }}" @selected(old('email_list_id', $campaign->email_list_id ?? null) == $list->id)>{{ $list->name }} ({{ $list->subscribers_count }})</option>
                    @endforeach
                </select>
                @error('email_list_id')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">From Name *</label>
                <input type="text" name="from_name" class="form-control" value="{{ old('from_name', $campaign->from_name ?? config('app.name')) }}" required>
                @error('from_name')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">From Email *</label>
                <input type="email" name="from_email" class="form-control" value="{{ old('from_email', $campaign->from_email ?? '') }}" placeholder="must be on your Mailgun sending domain" required>
                @error('from_email')<p class="text-danger small">{{ $message }}</p>@enderror
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
                <input type="hidden" name="email_template_id" id="emailTemplateId" value="{{ old('email_template_id', $campaign->email_template_id ?? '') }}">
                <label class="form-label">Body (HTML) *</label>
                <p class="text-muted small mb-2">Merge tags: <code>@{{name}}</code>, <code>@{{first_name}}</code>, <code>@{{email}}</code>, <code>@{{unsubscribe_url}}</code>. An unsubscribe footer is appended automatically.</p>
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
        <button type="submit" name="action" value="send_now" class="btn btn-primary" onclick="return confirm('Send this campaign to every subscribed contact in the selected list right now? This cannot be undone.');">Send Now</button>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const templates = @json($templatesJson);
        const templateSelect = document.getElementById('templateSelect');
        const templateIdInput = document.getElementById('emailTemplateId');
        const subject = document.getElementById('campaignSubject');
        const body = document.getElementById('campaignBody');
        const preview = document.getElementById('campaignPreview');

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

        window.requireScheduleTime = function () {
            if (!document.getElementById('scheduledAt').value) {
                alert('Pick a date/time to schedule this campaign for.');
                return false;
            }
            return true;
        };
    })();
</script>
@endpush
