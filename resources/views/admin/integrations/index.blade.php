@extends('layouts.app')

@section('title', 'Integrations')

@section('content')

@php
    $categoryLabels = [
        'analytics' => 'Analytics & Tracking',
        'pixels'    => 'Pixels',
        'ai'        => 'AI Integration',
        'ads'       => 'Ad Platforms',
    ];

    // JS payload for the single reusable modal - one row per integration,
    // including whether the current user is already connected and (if so)
    // that connection's own id, so Save/Disconnect know which record to
    // hit without a page reload between opening the modal and submitting.
    $modalData = $integrations->flatten(1)->mapWithKeys(function ($integration) {
        $ui = $integration->user_integration;

        return [$integration->id => [
            'id'                => $integration->id,
            'slug'              => $integration->slug,
            'name'              => $integration->name,
            'icon'              => $integration->icon_path,
            'credentialFields'  => $integration->credential_fields,
            'howTo'             => $integration->how_to,
            'isConnected'       => (bool) $ui,
            'userIntegrationId' => $ui->id ?? null,
            'storeUrl'          => route('admin.integrations.store', $integration),
        ]];
    });
@endphp

<div class="socialeaz-integrations">
    <div class="int-header mb-6">
        <h4 class="int-title mb-1">Integrations & Connected Services</h4>
        <p class="int-subtitle mb-0">Connect analytics, pixels, and AI services to power up your Socialeaz account.</p>
    </div>

    @forelse($integrations as $category => $items)
    <div class="mb-6">
        <h6 class="int-category-title mb-3">{{ $categoryLabels[$category] ?? ucfirst($category) }}</h6>
        <div class="row g-4">
            @foreach($items as $integration)
            @php $ui = $integration->user_integration; @endphp
            <div class="col-md-6 col-xl-4">
                <div class="int-card">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <span class="int-icon int-icon-{{ $integration->slug }}"><i class="bx {{ $integration->icon_path }}"></i></span>
                        @if($ui)
                        <span class="int-badge int-badge-connected"><span class="dot"></span> Connected</span>
                        @else
                        <span class="int-badge int-badge-disconnected">Not Connected</span>
                        @endif
                    </div>
                    <div class="int-card-name">{{ $integration->name }}</div>
                    <p class="int-card-desc">{{ $integration->description }}</p>
                    <button type="button" class="int-btn {{ $ui ? 'int-btn-ghost' : 'int-btn-primary' }}" data-integration-id="{{ $integration->id }}" data-bs-toggle="modal" data-bs-target="#integrationModal">
                        {{ $ui ? 'Configure' : 'Connect' }}
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @empty
    <p class="text-muted">No integrations available yet.</p>
    @endforelse
</div>

<!-- Reusable service modal -->
<div class="modal fade" id="integrationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content int-modal">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span id="intModalIcon" class="int-icon"></span>
                    <span id="intModalTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <ul class="int-tabs" role="tablist">
                <li><button type="button" class="active" data-bs-toggle="tab" data-bs-target="#intTabHowTo">How To</button></li>
                <li><button type="button" data-bs-toggle="tab" data-bs-target="#intTabSetup">Setup / API Keys</button></li>
            </ul>
            <div class="modal-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="intTabHowTo">
                        <p id="intHowToText" class="mb-3"></p>
                        <a id="intHowToLink" href="#" target="_blank" rel="noopener" class="int-link">
                            View documentation <i class="bx bx-link-external"></i>
                        </a>
                    </div>
                    <div class="tab-pane fade" id="intTabSetup">
                        <form id="intForm">
                            <div id="intFormFields"></div>
                            <div class="d-flex align-items-center gap-2 mt-4">
                                <button type="submit" class="int-btn int-btn-primary flex-fill">Save</button>
                                <button type="button" id="intDisconnectBtn" class="int-btn int-btn-danger d-none">Disconnect</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.socialeaz-integrations {
    --int-bg: #f5f5fa;
    --int-card: #ffffff;
    --int-card-hover: #f7f7fc;
    --int-border: rgba(20,20,40,.08);
    --int-text: #4b4d5c;
    --int-heading: #1e1e2d;
    --int-muted: #8b8d9c;
    --int-primary: #7c5cff;
    --int-primary-2: #a855f7;
    --int-success: #16a34a;
    --int-danger: #e11d48;

    background: var(--int-bg);
    color: var(--int-text);
    border-radius: 1rem;
    padding: 1.5rem;
    margin: -1.5rem;
    min-height: calc(100vh - 8rem);
}
.socialeaz-integrations .int-title { color: var(--int-heading); font-weight: 700; }
.socialeaz-integrations .int-subtitle { color: var(--int-muted); }
.socialeaz-integrations .int-category-title { color: var(--int-heading); font-weight: 600; text-transform: uppercase; font-size: .75rem; letter-spacing: .04em; }

.socialeaz-integrations .int-card {
    background: var(--int-card); border: 1px solid var(--int-border); border-radius: .85rem; padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(20,20,50,.04); height: 100%; display: flex; flex-direction: column;
}
.socialeaz-integrations .int-card-name { color: var(--int-heading); font-weight: 600; font-size: .95rem; margin-bottom: .35rem; }
.socialeaz-integrations .int-card-desc { color: var(--int-muted); font-size: .8125rem; flex: 1; margin-bottom: 1rem; }

.socialeaz-integrations .int-icon, .int-modal .int-icon {
    width: 44px; height: 44px; border-radius: .7rem; display: inline-flex; align-items: center; justify-content: center;
    font-size: 20px; color: #fff; background: var(--int-primary); flex-shrink: 0;
}
.socialeaz-integrations .int-icon-google_analytics, .int-modal .int-icon-google_analytics { background: #F9AB00; }
.socialeaz-integrations .int-icon-microsoft_clarity, .int-modal .int-icon-microsoft_clarity { background: #0078D4; }
.socialeaz-integrations .int-icon-facebook_pixel, .int-modal .int-icon-facebook_pixel { background: #1877F2; }
.socialeaz-integrations .int-icon-snapchat_pixel, .int-modal .int-icon-snapchat_pixel { background: #FFFC00; color: #000; }
.socialeaz-integrations .int-icon-tiktok_pixel, .int-modal .int-icon-tiktok_pixel { background: #000000; }
.socialeaz-integrations .int-icon-x_pixel, .int-modal .int-icon-x_pixel { background: #000000; }
.socialeaz-integrations .int-icon-claude_ai, .int-modal .int-icon-claude_ai { background: #D97757; }
.socialeaz-integrations .int-icon-chatgpt_ai, .int-modal .int-icon-chatgpt_ai { background: #10A37F; }
.socialeaz-integrations .int-icon-google_ads, .int-modal .int-icon-google_ads { background: #4285F4; }
.socialeaz-integrations .int-icon-google_tag_manager, .int-modal .int-icon-google_tag_manager { background: #246FDB; }

.socialeaz-integrations .int-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .68rem; font-weight: 600; padding: .2rem .55rem; border-radius: 1rem; }
.socialeaz-integrations .int-badge-connected { background: rgba(22,163,74,.1); color: var(--int-success); }
.socialeaz-integrations .int-badge-connected .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--int-success); display: inline-block; }
.socialeaz-integrations .int-badge-disconnected { background: rgba(139,141,156,.12); color: var(--int-muted); }

.socialeaz-integrations .int-btn, .int-modal .int-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .375rem;
    border-radius: .5rem; padding: .5rem .9rem; font-size: .8125rem; font-weight: 600;
    border: 1px solid var(--int-border); cursor: pointer;
}
.socialeaz-integrations .int-btn-primary, .int-modal .int-btn-primary { background: linear-gradient(135deg, var(--int-primary), var(--int-primary-2)); color: #fff; border: none; }
.socialeaz-integrations .int-btn-primary:hover, .int-modal .int-btn-primary:hover { opacity: .92; color: #fff; }
.socialeaz-integrations .int-btn-ghost { background: var(--int-card-hover); color: var(--int-text); }
.socialeaz-integrations .int-btn-ghost:hover { color: var(--int-primary); border-color: var(--int-primary); }
.int-modal .int-btn-danger { background: #fff; color: var(--int-danger); border-color: rgba(225,29,72,.3); }
.int-modal .int-btn-danger:hover { background: rgba(225,29,72,.08); }

.int-modal .int-tabs { display: flex; gap: 1rem; list-style: none; padding: 0 1.5rem; margin: 0; border-bottom: 1px solid var(--int-border); }
.int-modal .int-tabs button { background: none; border: none; color: var(--int-muted); padding: .65rem .1rem; font-size: .85rem; font-weight: 500; border-bottom: 2px solid transparent; }
.int-modal .int-tabs button.active { color: var(--int-primary); border-color: var(--int-primary); }
.int-modal .int-link { color: var(--int-primary); font-size: .8125rem; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: .3rem; }
.int-modal .int-link:hover { text-decoration: underline; }
.int-modal .form-label { font-size: .8125rem; font-weight: 600; color: var(--int-heading); }
.int-modal .form-control { font-size: .85rem; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var integrations = @json($modalData);
    var current = null;

    var modalEl = document.getElementById('integrationModal');
    var iconEl = document.getElementById('intModalIcon');
    var titleEl = document.getElementById('intModalTitle');
    var howToTextEl = document.getElementById('intHowToText');
    var howToLinkEl = document.getElementById('intHowToLink');
    var fieldsEl = document.getElementById('intFormFields');
    var formEl = document.getElementById('intForm');
    var disconnectBtn = document.getElementById('intDisconnectBtn');

    modalEl.addEventListener('show.bs.modal', function (event) {
        var id = event.relatedTarget.getAttribute('data-integration-id');
        current = integrations[id];
        if (!current) return;

        iconEl.className = 'int-icon int-icon-' + current.slug;
        iconEl.innerHTML = '<i class="bx ' + current.icon + '"></i>';
        titleEl.textContent = current.name;

        howToTextEl.textContent = current.howTo.text;
        if (current.howTo.url) {
            howToLinkEl.href = current.howTo.url;
            howToLinkEl.classList.remove('d-none');
        } else {
            howToLinkEl.classList.add('d-none');
        }

        fieldsEl.innerHTML = current.credentialFields.map(function (field) {
            return '' +
                '<div class="mb-3">' +
                '  <label class="form-label">' + field.label + '</label>' +
                '  <input type="' + field.type + '" class="form-control" name="' + field.key + '" placeholder="' + field.placeholder + '" ' + (current.isConnected ? '' : 'required') + '>' +
                (current.isConnected ? '<small class="text-muted">Already saved - leave blank to keep the current value, or enter a new one to replace it.</small>' : '') +
                '</div>';
        }).join('');

        if (current.isConnected) {
            disconnectBtn.classList.remove('d-none');
        } else {
            disconnectBtn.classList.add('d-none');
        }
    });

    formEl.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!current) return;

        var formData = new FormData(formEl);
        var payload = { credentials: {} };
        formData.forEach(function (value, key) { payload.credentials[key] = value; });

        fetch(current.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(payload),
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
            if (result.ok && result.data.success) {
                Swal.fire('Success', result.data.message, 'success').then(function () { location.reload(); });
            } else {
                var message = result.data.message || 'Please check the values you entered.';
                Swal.fire('Error', message, 'error');
            }
        })
        .catch(function () {
            Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
        });
    });

    disconnectBtn.addEventListener('click', function () {
        if (!current || !current.userIntegrationId) return;

        Swal.fire({
            title: 'Disconnect ' + current.name + '?',
            text: 'You will need to re-enter the credential to reconnect it.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Disconnect',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            fetch('{{ url("admin/integrations/connections") }}/' + current.userIntegrationId, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                Swal.fire('Disconnected', data.message, 'success').then(function () { location.reload(); });
            })
            .catch(function () {
                Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
            });
        });
    });
});
</script>
@endpush
