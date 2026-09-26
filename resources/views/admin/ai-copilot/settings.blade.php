@extends('layouts.app')

@section('title', 'AI Copilot Settings')

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="dash-card mb-3">
        <h4 class="mb-1">AI Copilot Settings</h4>
        <p class="text-muted mb-0">Let the AI Copilot answer your customers automatically from your <a href="{{ route('admin.knowledge-base.index') }}">Knowledge Base</a> and <a href="{{ route('admin.ai-copilot.business-profile.edit') }}">Business Profile</a>. It only ever sends an answer it's confident about, using your own published FAQs word-for-word - it never invents information.</p>
    </div>

    <div class="dash-card">
        <form id="ai-copilot-settings-form">
            @csrf

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="ai_enabled" name="ai_enabled" {{ $settings->ai_enabled ? 'checked' : '' }}>
                <label class="form-check-label" for="ai_enabled"><strong>Enable AI Copilot</strong></label>
                <div class="form-text">Turns on confidence scoring for your incoming customer messages. Off by default.</div>
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="auto_reply_enabled" name="auto_reply_enabled" {{ $settings->auto_reply_enabled ? 'checked' : '' }}>
                <label class="form-check-label" for="auto_reply_enabled"><strong>Allow automatic replies</strong></label>
                <div class="form-text">When on, a high-confidence match is sent to the customer automatically. When off, AI Copilot still scores matches (visible in the inbox) but never sends anything without you.</div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="confidence_threshold_auto">Auto-reply confidence threshold</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="confidence_threshold_auto" name="confidence_threshold_auto" min="0" max="100" value="{{ $settings->confidence_threshold_auto }}" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">A match scoring at or above this is confident enough to send unattended.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="confidence_threshold_suggested">Suggestion threshold</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="confidence_threshold_suggested" name="confidence_threshold_suggested" min="0" max="100" value="{{ $settings->confidence_threshold_suggested }}" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Below this, no match is confident enough to even suggest - the conversation stays in your inbox for you.</div>
                </div>
            </div>

            <button type="submit" class="dash-btn dash-btn-primary">Save Settings</button>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('ai-copilot-settings-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const form = e.target;
    const payload = {
        ai_enabled: form.ai_enabled.checked,
        auto_reply_enabled: form.auto_reply_enabled.checked,
        confidence_threshold_auto: form.confidence_threshold_auto.value,
        confidence_threshold_suggested: form.confidence_threshold_suggested.value,
    };

    fetch('{{ route('admin.ai-copilot.settings.update') }}', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('[name=_token]').value,
        },
        body: JSON.stringify(payload),
    })
        .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Could not save settings.');
            }
            window.Swal?.fire({ icon: 'success', title: data.message || 'Saved.', timer: 1800, showConfirmButton: false });
        })
        .catch((err) => {
            window.Swal?.fire({ icon: 'error', title: 'Error', text: err.message });
        });
});
</script>
@endpush
