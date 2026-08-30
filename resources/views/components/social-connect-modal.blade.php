{{--
    Shared "Connect Social Accounts" popup - previously copy-pasted between
    admin/ads/dashboard.blade.php (#socialConnectModal) and
    admin/posts/dashboard.blade.php (#addAccountModal), which is how those
    two ended up drifting out of sync in the first place. Both pages now
    render this one component and only supply the data that's actually
    different between them (which platforms, which URLs, whether an
    account is already connected) - the markup, classes, and copy live
    in exactly one place.

    Props:
      id         - modal element id (each caller needs its own, since a
                   page can only have one element with a given id)
      title      - defaults to the existing "Connect Social Accounts" copy
      subtitle   - defaults to the existing "Manage all your connected
                   platforms" copy
      platforms  - array of:
                     key           string, e.g. 'facebook'
                     class         CSS class matching a global
                                   .social-icon-mini.{class} brand color
                                   (assets/css/admin.css)
                     icon          Boxicon suffix, e.g. 'bxl-facebook'
                     label         display name, e.g. 'Facebook'
                     url           where the tile links
                     connected     bool, optional (default false) -
                                   switches the small text to the
                                   "connected" styling/copy
                     connectedText string, optional override for the
                                   connected-state text (falls back to
                                   the shared "See All running campaigns"
                                   copy)
--}}
@props([
    'id',
    'title' => __('admin.marketing_tools.ads.accounts.connect_header'),
    'subtitle' => __('admin.marketing_tools.ads.accounts.manage_account_description'),
    'platforms' => [],
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg social-modal">
            <div class="modal-header border-0 pb-0 mt-0 pt-0">
                <div>
                    <h4 class="mb-1 font-weight-bold mb-0 mt-0">{{ $title }}</h4>
                    <small class="text-muted">{{ $subtitle }}</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-4">
                <div class="row">
                    @foreach($platforms as $platform)
                    <div class="col-6 col-md-2 mb-3">
                        <div class="social-card-mini">
                            <a href="{{ $platform['url'] }}">
                                <div class="social-icon-mini {{ $platform['class'] }}">
                                    <i class="bx {{ $platform['icon'] }}"></i>
                                </div>
                                <h6 class="mt-2 mb-1">{{ $platform['label'] }}</h6>
                                @if($platform['connected'] ?? false)
                                    <small class="connected-text">
                                        {{ $platform['connectedText'] ?? __('admin.marketing_tools.ads.accounts.see_all_running_campaigns') }}
                                    </small>
                                @else
                                    <small class="disconnected-text">{{ __('admin.marketing_tools.ads.accounts.connect') }}</small>
                                @endif
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
