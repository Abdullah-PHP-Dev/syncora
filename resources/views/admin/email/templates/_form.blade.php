{{-- Shared by create.blade.php and edit.blade.php so both stay in sync --}}
<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Template Name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $template->name ?? '') }}" required>
                @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Subject *</label>
                <input type="text" name="subject" class="form-control" value="{{ old('subject', $template->subject ?? '') }}" required>
                @error('subject')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-control" value="{{ old('category', $template->category ?? '') }}" placeholder="e.g. Newsletter, Promo">
            </div>
            <div class="col-12">
                <label class="form-label">Body (HTML) *</label>
                <p class="text-muted small mb-2">
                    Personalization tags (substituted by SendGrid against each contact's synced fields when this template is used in a campaign):
                    <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="{{ '{{first_name}}' }}">first_name</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="{{ '{{last_name}}' }}">last_name</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary insert-tag" data-tag="{{ '{{email}}' }}">email</button>
                </p>
                <textarea name="body" id="templateBody" class="form-control" rows="16" required>{{ old('body', $template->body ?? '') }}</textarea>
                @error('body')<p class="text-danger small">{{ $message }}</p>@enderror
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center">
                <label class="form-label mb-0">Live Preview</label>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" data-width="100%">Desktop</button>
                    <button type="button" class="btn btn-outline-secondary" data-width="375px">Mobile</button>
                </div>
            </div>
            <div class="col-12">
                <iframe id="templatePreview" style="height:320px;width:100%;border:1px solid rgba(0,0,0,.1);border-radius:8px;transition:width .2s;"></iframe>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">Save Template</button>
    </div>
</div>

@push('scripts')
<script>
    window.addEventListener('load', function () {
        const body = document.getElementById('templateBody');
        const preview = document.getElementById('templatePreview');

        function render() {
            preview.srcdoc = body.value;
        }

        body.addEventListener('input', render);
        render();

        document.querySelectorAll('.insert-tag').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const start = body.selectionStart;
                const end = body.selectionEnd;
                body.value = body.value.slice(0, start) + btn.dataset.tag + body.value.slice(end);
                render();
                body.focus();
            });
        });

        document.querySelectorAll('[data-width]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('[data-width]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                preview.style.width = btn.dataset.width;
            });
        });
    });
</script>
@endpush
