{{--
    Shared "Connect Google Chat" form - extracted out of
    admin/chats/channels.blade.php for the same reason as
    whatsapp-connect-modal.blade.php: reused as-is by the new Manage
    Channels quick-modal on admin/chats/dashboard.blade.php.

    Google Chat has two real connect paths (see channels.blade.php's
    surrounding platform-card): this service-account-JSON form, which is
    the one that can actually receive customer DMs, and a separate OAuth
    link (admin.messaging.auth.google_chat.redirect) that only reads/
    posts in spaces the signed-in user already belongs to. Only this form
    is included here - the OAuth link is deliberately NOT offered from
    the quick-modal, since presenting it there without that caveat would
    look like a shortcut to the same result it isn't.

    Props: id - modal element id (default 'googleChatModal')
--}}
@props(['id' => 'googleChatModal'])

<div class="modal fade" id="{{ $id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('admin.messaging.channels.google_chat.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center"><span class="modal-icon-badge" style="background:#4285F4"><i class="bx bx-message-rounded-dots"></i></span> Connect Google Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">In <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a>, enable the Google Chat API, create a service account, and download its JSON key - paste the whole file below. After connecting, you'll be shown an App URL - paste it into the Chat API's Configuration tab.</p>
                    <div class="mb-3">
                        <label class="form-label">Display Name *</label>
                        <input type="text" name="name" class="form-control" required>
                        @error('name')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Service Account JSON Key *</label>
                        <textarea name="service_account_json" class="form-control" rows="5" placeholder='{"type": "service_account", "client_email": "...", "private_key": "...", ...}' required></textarea>
                        @error('service_account_json')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Google Cloud Project Number</label>
                        <input type="text" name="project_number" class="form-control" placeholder="Usually auto-detected - only needed if we can't detect it">
                        <small class="text-muted">We'll try to detect this automatically from your service account. If we can't, find it under "Project info" on your project's Google Cloud dashboard (not the project ID) and enter it here.</small>
                        @error('project_number')<p class="text-danger small">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-sm text-white w-100" style="background:#4285F4">Connect</button>
                </div>
            </form>
        </div>
    </div>
</div>
