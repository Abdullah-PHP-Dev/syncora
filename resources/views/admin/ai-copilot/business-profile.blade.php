@extends('layouts.app')

@section('title', 'Business Profile')

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@php
    $days = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
    $hours = $profile->business_hours ?? [];
    $closedDays = $hours['closed'] ?? [];
@endphp

@section('content')
<div class="socialeaz-dash">

    <div class="dash-card mb-3">
        <h4 class="mb-1">Business Profile</h4>
        <p class="text-muted mb-0">Structured facts about your business - the AI Copilot checks these before ever guessing an answer. Saving here automatically adds/updates matching entries in your <a href="{{ route('admin.knowledge-base.index') }}">Knowledge Base</a>.</p>
    </div>

    <div class="dash-card">
        <form id="business-profile-form">
            @csrf

            <div class="mb-4">
                <label class="form-label" for="business_name">Business name</label>
                <input type="text" class="form-control" id="business_name" name="business_name" maxlength="150" value="{{ $profile->business_name }}">
            </div>

            <div class="mb-4">
                <label class="form-label">Business hours</label>
                <div class="row g-2">
                    @foreach ($days as $key => $label)
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text" style="width:120px;">{{ $label }}</span>
                                <input type="text" class="form-control" name="hours[{{ $key }}]" placeholder="9:00-18:00" value="{{ $hours[$key] ?? '' }}" {{ in_array($key, $closedDays) ? 'disabled' : '' }}>
                                <span class="input-group-text">
                                    <input class="form-check-input mt-0 closed-toggle" type="checkbox" name="closed[]" value="{{ $key }}" data-day="{{ $key }}" {{ in_array($key, $closedDays) ? 'checked' : '' }} title="Closed">
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="form-text">Check the box for a day you're closed. Leave a field blank if it's simply unknown.</div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="phone">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone" maxlength="30" value="{{ $profile->phone }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="address">Address</label>
                    <input type="text" class="form-control" id="address" name="address" maxlength="255" value="{{ $profile->address }}">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="delivery_policy">Delivery policy</label>
                <textarea class="form-control" id="delivery_policy" name="delivery_policy" rows="3" maxlength="5000">{{ $profile->delivery_policy }}</textarea>
            </div>

            <div class="mb-4">
                <label class="form-label" for="return_policy">Return / refund policy</label>
                <textarea class="form-control" id="return_policy" name="return_policy" rows="3" maxlength="5000">{{ $profile->return_policy }}</textarea>
            </div>

            <button type="submit" class="dash-btn dash-btn-primary">Save Business Profile</button>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.querySelectorAll('.closed-toggle').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        const input = this.closest('.input-group').querySelector('input[type="text"]');
        input.disabled = this.checked;
    });
});

document.getElementById('business-profile-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const payload = { hours: {}, closed: [] };

    formData.forEach((value, key) => {
        if (key.startsWith('hours[')) {
            const day = key.match(/\[(.+)\]/)[1];
            payload.hours[day] = value;
        } else if (key === 'closed[]') {
            payload.closed.push(value);
        } else if (key !== '_token') {
            payload[key] = value;
        }
    });

    fetch('{{ route('admin.ai-copilot.business-profile.update') }}', {
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
                throw new Error(data.message || 'Could not save your Business Profile.');
            }
            window.Swal?.fire({ icon: 'success', title: data.message || 'Saved.', timer: 1800, showConfirmButton: false });
        })
        .catch((err) => {
            window.Swal?.fire({ icon: 'error', title: 'Error', text: err.message });
        });
});
</script>
@endpush
