{{-- Shared by create.blade.php and edit.blade.php so both stay in sync --}}
<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Template Name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $template->name ?? '') }}" required>
                @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">Subject *</label>
                <input type="text" name="subject" class="form-control" value="{{ old('subject', $template->subject ?? '') }}" required>
                @error('subject')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Body (HTML) *</label>
                <p class="text-muted small mb-2">Merge tags: <code>@{{name}}</code>, <code>@{{first_name}}</code>, <code>@{{email}}</code>, <code>@{{unsubscribe_url}}</code></p>
                <textarea name="body" id="templateBody" class="form-control" rows="16" required>{{ old('body', $template->body ?? '') }}</textarea>
                @error('body')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Live Preview</label>
                <iframe id="templatePreview" class="w-100" style="height:320px;border:1px solid rgba(0,0,0,.1);border-radius:8px;"></iframe>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">Save Template</button>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const body = document.getElementById('templateBody');
        const preview = document.getElementById('templatePreview');

        function render() {
            preview.srcdoc = body.value;
        }

        body.addEventListener('input', render);
        render();
    })();
</script>
@endpush
