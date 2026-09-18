{{--
    Shared "Connect WhatsApp Number" form - extracted out of
    admin/chats/channels.blade.php so the new Manage Channels quick-modal
    on admin/chats/dashboard.blade.php can reuse the exact same form
    instead of a second copy drifting out of sync (the same reasoning
    documented on components/social-connect-modal.blade.php).

    WhatsApp has no plain OAuth redirect in this app - the real connect
    mechanism is pasting a Phone Number ID + permanent access token from a
    Meta Business System User, so unlike Facebook/Instagram/X/TikTok this
    stays a form, not a link.

    Props: id - modal element id (default 'whatsappModal', each caller
                 needs its own if a page ever renders this twice)
--}}
@props(['id' => 'whatsappModal'])

<div class="modal fade" id="{{ $id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.messaging.channels.whatsapp.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#25D366"><i class="bx bxl-whatsapp"></i></span> Connect WhatsApp Number</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Display Name *</label>
                        <input type="text" name="name" class="form-control" required>
                        @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number ID *</label>
                        <input type="text" name="phone_number_id" class="form-control" required>
                        @error('phone_number_id')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Permanent Access Token *</label>
                        <input type="text" name="access_token" class="form-control" required>
                        @error('access_token')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">Connect</button>
                </div>
            </form>
        </div>
    </div>
</div>
