@extends('layouts.apps')
@section('title', __('seller_team.join.title'))
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/auth-home.css') }}?v={{ filemtime(public_path('assets/css/auth-home.css')) }}">
<style>
    .inv-list{list-style:none;margin:0 0 20px;padding:0;display:flex;flex-direction:column;gap:8px}
    .inv-list li{display:flex;align-items:center;gap:10px;border:1px solid #ece8f7;border-radius:12px;padding:10px 12px;background:#faf8ff;font-size:14px}
    .inv-list i{color:#720aff;font-size:17px}
    .inv-list strong{flex:1;min-width:0;overflow-wrap:anywhere}
    .inv-role{font-size:12px;font-weight:700;color:#720aff;background:#f1eaff;border-radius:999px;padding:2px 10px}
    .inv-email{display:flex;align-items:center;gap:10px;border:1px solid #e4e3ee;border-radius:12px;padding:12px 14px;background:#f7f7fb;color:#4b4a63;font-size:14px;overflow-wrap:anywhere}
    .inv-secondary{display:block;text-align:center;margin-top:14px;font-size:14px}
</style>
@endpush

@section('content')
<div class="theme-page auth-home">
    <div class="auth-home-shell auth-home-login">
        @include('auth.partials.workspace-visual', ['mode' => 'login'])
        <section class="auth-form-section" aria-labelledby="auth-form-title">
            <div class="auth-form-container">
            @if($state === 'invalid')
                <div class="auth-form-header">
                    <div class="auth-form-badge"><i class="bi bi-link-45deg"></i></div>
                    <h2 id="auth-form-title">{{ __('seller_team.join.invalid_title') }}</h2>
                    <p>{{ __('seller_team.join.invalid_help') }}</p>
                </div>
                <a class="auth-submit" href="{{ route('login') }}"><span>{{ __('Sign in') }}</span><i class="bi bi-arrow-right"></i></a>
            @else
                <div class="auth-form-header">
                    <div class="auth-form-badge"><i class="bi bi-people"></i></div>
                    <h2 id="auth-form-title">{{ __('seller_team.join.heading', ['name' => $inviter->name]) }}</h2>
                    <p>{{ __('seller_team.join.sub') }}</p>
                </div>

                @if ($errors->any())
                    <div class="auth-alert auth-alert-danger"><i class="bi bi-exclamation-circle"></i><div><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
                @endif

                <ul class="inv-list" data-invited-workspaces>
                    @foreach($access as $row)
                        <li><i class="bi bi-buildings" aria-hidden="true"></i><strong>{{ $row->workspace_name }}</strong><span class="inv-role">{{ __('seller_team.roles.'.$row->role) }}</span></li>
                    @endforeach
                </ul>

                @if($state === 'join')
                    {{-- New teammate: the link proves the email, so they only choose a password. --}}
                    <form method="POST" action="{{ route('seller-workspaces.join', ['token' => $token]) }}" class="auth-form">
                        @csrf
                        <div class="auth-field">
                            <label>{{ __('Email address') }}</label>
                            <div class="inv-email"><i class="bi bi-envelope" aria-hidden="true"></i><span dir="ltr">{{ $member->email }}</span></div>
                        </div>
                        <div class="auth-field">
                            <label for="name">{{ __('seller_team.full_name') }}</label>
                            <div class="auth-input-wrap"><i class="bi bi-person"></i><input type="text" id="name" name="name" value="{{ old('name', $member->name) }}" autocomplete="name" maxlength="120" required></div>
                        </div>
                        <div class="auth-field">
                            <label for="password">{{ __('seller_team.join.password') }}</label>
                            <div class="auth-input-wrap"><i class="bi bi-lock"></i><input type="password" id="password" name="password" autocomplete="new-password" required><button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="{{ __('Show password') }}"><i class="bi bi-eye"></i></button></div>
                        </div>
                        <div class="auth-field">
                            <label for="password_confirmation">{{ __('Confirm password') }}</label>
                            <div class="auth-input-wrap"><i class="bi bi-lock"></i><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
                        </div>
                        <div class="auth-remember">
                            <label class="auth-checkbox"><input type="checkbox" name="terms" value="1" @checked(old('terms')) required><span></span>{{ __('By continuing, you agree to our') }} <a href="{{ route('terms') }}" target="_blank" rel="noopener">{{ __('Terms') }}</a></label>
                        </div>
                        <button type="submit" class="auth-submit"><span>{{ __('seller_team.join.submit') }}</span><i class="bi bi-arrow-right"></i></button>
                    </form>
                @elseif($state === 'signin')
                    <p class="text-muted">{{ __('seller_team.join.signin_help', ['email' => $member->email]) }}</p>
                    <form method="GET" action="{{ route('login') }}">
                        <button type="submit" class="auth-submit"><span>{{ __('seller_team.join.signin') }}</span><i class="bi bi-arrow-right"></i></button>
                    </form>
                @elseif($state === 'accept')
                    <form method="POST" action="{{ route('seller-workspaces.accept', ['token' => $token]) }}">
                        @csrf
                        <button type="submit" class="auth-submit"><span>{{ __('seller_team.accept') }}</span><i class="bi bi-arrow-right"></i></button>
                    </form>
                    <a class="inv-secondary" href="{{ route('seller-workspaces.index') }}">{{ __('seller_team.workspaces') }}</a>
                @else
                    <div class="auth-alert auth-alert-danger"><i class="bi bi-exclamation-circle"></i><span>{{ __('seller_team.join.wrong', ['current' => $user->email, 'email' => $member->email]) }}</span></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="auth-submit"><span>{{ __('seller_team.join.switch') }}</span><i class="bi bi-box-arrow-right"></i></button>
                    </form>
                @endif
            @endif
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) return;
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
</script>
@endpush
