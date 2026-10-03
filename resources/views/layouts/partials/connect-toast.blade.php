{{--
    Shown once after an account-connect (OAuth) round trip that returned
    the user to the page they started from - see App\Http\Middleware\
    ReturnToOrigin. Success auto-hides; an error stays with "Try again".
--}}
@if ($notice = session('connect_notice'))
<div class="se-connect-toast is-{{ $notice['type'] }}" role="{{ $notice['type'] === 'error' ? 'alert' : 'status' }}" id="seConnectToast">
    <span class="se-connect-toast-icon"><i class="bx {{ $notice['type'] === 'error' ? 'bx-error-circle' : 'bx-check-circle' }}"></i></span>
    <div class="se-connect-toast-body">
        <strong>{{ $notice['title'] }}</strong>
        <span>{{ $notice['message'] }}</span>
        @if (!empty($notice['retry']))
            <a href="{{ $notice['retry'] }}" class="se-connect-toast-retry"><i class="bx bx-refresh"></i> {{ __('Try again') }}</a>
        @endif
    </div>
    <button type="button" class="se-connect-toast-close" aria-label="{{ __('Close') }}" onclick="this.closest('.se-connect-toast').remove()"><i class="bx bx-x"></i></button>
</div>
<style>
    .se-connect-toast { position: fixed; z-index: 2000; bottom: 24px; inset-inline-end: 24px; width: min(400px, calc(100vw - 32px)); display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; background: #fff; border: 1px solid #e7e9f0; border-radius: 14px; box-shadow: 0 16px 40px rgba(16, 24, 40, .16); animation: seToastIn .25s ease-out; }
    .se-connect-toast-icon { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 20px; flex-shrink: 0; }
    .se-connect-toast.is-success .se-connect-toast-icon { background: #ecfdf3; color: #079455; }
    .se-connect-toast.is-error .se-connect-toast-icon { background: #fef3f2; color: #d92d20; }
    .se-connect-toast-body { flex: 1; min-width: 0; font-size: .82rem; color: #545d70; line-height: 1.45; }
    .se-connect-toast-body strong { display: block; color: #161b2b; font-size: .88rem; margin-bottom: 2px; }
    .se-connect-toast-retry { display: inline-flex; align-items: center; gap: 4px; margin-top: 8px; font-weight: 600; color: #6d4aff; text-decoration: none; }
    .se-connect-toast-close { border: none; background: transparent; color: #8a92a3; width: 26px; height: 26px; border-radius: 7px; display: grid; place-items: center; font-size: 1.1rem; }
    .se-connect-toast-close:hover { background: #f1f3f7; color: #161b2b; }
    @keyframes seToastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
</style>
@if ($notice['type'] === 'success')
<script>setTimeout(function () { var t = document.getElementById('seConnectToast'); if (t) { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 300); } }, 6000);</script>
@endif
@endif
