@extends('layouts.app')

@section('title', 'Knowledge Gaps')

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@php
    $badgeClass = [
        'new' => 'bg-danger', 'under_review' => 'bg-warning text-dark',
        'faq_created' => 'bg-success', 'ignored' => 'bg-secondary', 'resolved' => 'bg-success',
    ];
@endphp

@section('content')
<div class="socialeaz-dash">

    <div class="dash-card mb-3">
        <h4 class="mb-1">Knowledge Gaps</h4>
        <p class="text-muted mb-0">Questions your AI Copilot couldn't confidently answer for your customers. Turn a recurring one into a FAQ - it's added as a draft in your <a href="{{ route('admin.knowledge-base.index') }}">Knowledge Base</a> for you to review before it's used to answer anyone.</p>
    </div>

    <div class="dash-card">
        @if ($gaps->isEmpty())
            <p class="text-muted mb-0">No knowledge gaps yet. As your AI Copilot runs, any customer question it can't confidently match against your Knowledge Base will show up here.</p>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Seen</th>
                            <th>Last occurred</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gaps as $gap)
                            <tr>
                                <td>{{ $gap->question }}</td>
                                <td>{{ $gap->occurrence_count }}</td>
                                <td>{{ $gap->last_occurred_at->diffForHumans() }}</td>
                                <td><span class="badge {{ $badgeClass[$gap->status] ?? 'bg-secondary' }}">{{ ucfirst(str_replace('_', ' ', $gap->status)) }}</span></td>
                                <td class="text-end">
                                    @if (!in_array($gap->status, ['faq_created', 'ignored', 'resolved']))
                                        <button type="button" class="dash-btn dash-btn-primary btn-sm create-faq-btn"
                                            data-gap-id="{{ $gap->id }}" data-question="{{ $gap->question }}"
                                            data-url="{{ route('admin.ai-copilot.knowledge-gaps.convert-to-faq', $gap) }}">
                                            Create FAQ
                                        </button>
                                        @if ($gap->status === 'new')
                                            <button type="button" class="dash-btn dash-btn-ghost btn-sm status-action-btn"
                                                data-url="{{ route('admin.ai-copilot.knowledge-gaps.under-review', $gap) }}">
                                                Under Review
                                            </button>
                                        @else
                                            <button type="button" class="dash-btn dash-btn-ghost btn-sm status-action-btn"
                                                data-url="{{ route('admin.ai-copilot.knowledge-gaps.resolve', $gap) }}">
                                                Resolved
                                            </button>
                                        @endif
                                        <button type="button" class="dash-btn dash-btn-ghost btn-sm status-action-btn"
                                            data-url="{{ route('admin.ai-copilot.knowledge-gaps.ignore', $gap) }}">
                                            Dismiss
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">Showing {{ $gaps->firstItem() }} to {{ $gaps->lastItem() }} of {{ $gaps->total() }}</div>
                {{ $gaps->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

</div>

<div class="modal fade" id="createFaqModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="create-faq-form">
                <div class="modal-header">
                    <h5 class="modal-title">Create FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Question</label>
                        <input type="text" class="form-control" id="modal-question" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="modal-category">Category (optional)</label>
                        <select class="form-select" id="modal-category" name="faq_category_id">
                            <option value="">No category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="modal-answer">Answer</label>
                        <textarea class="form-control" id="modal-answer" name="answer" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="dash-btn dash-btn-primary">Save Draft FAQ</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const createFaqModalEl = document.getElementById('createFaqModal');
const createFaqModal = new bootstrap.Modal(createFaqModalEl);
let activeConvertUrl = null;

document.querySelectorAll('.create-faq-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        activeConvertUrl = this.dataset.url;
        document.getElementById('modal-question').value = this.dataset.question;
        document.getElementById('modal-answer').value = '';
        document.getElementById('modal-category').value = '';
        createFaqModal.show();
    });
});

document.getElementById('create-faq-form').addEventListener('submit', function (e) {
    e.preventDefault();

    fetch(activeConvertUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            answer: document.getElementById('modal-answer').value,
            faq_category_id: document.getElementById('modal-category').value || null,
        }),
    })
        .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Could not create the FAQ.');
            }
            createFaqModal.hide();
            window.Swal.fire({ icon: 'success', title: data.message, timer: 2500, showConfirmButton: false })
                .then(() => window.location.reload());
        })
        .catch((err) => window.Swal.fire({ icon: 'error', title: 'Error', text: err.message }));
});

document.querySelectorAll('.status-action-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        fetch(this.dataset.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        })
            .then((response) => response.json())
            .then(() => window.location.reload())
            .catch((err) => window.Swal.fire({ icon: 'error', title: 'Error', text: err.message }));
    });
});
</script>
@endpush
