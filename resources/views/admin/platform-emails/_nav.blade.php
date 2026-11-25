<header class="account-heading pe-head">
    <div>
        <span class="account-eyebrow">{{ __('platform_emails.section') }}</span>
        <h1>{{ $title }}</h1>
        @isset($intro)<p>{{ $intro }}</p>@endisset
    </div>
    <nav class="pe-tabs" aria-label="{{ __('platform_emails.section') }}">
        <a href="{{ route('admin.platform-emails.index') }}" @class(['active' => request()->routeIs('admin.platform-emails.index', 'admin.platform-emails.edit')])>{{ __('platform_emails.title') }}</a>
        <a href="{{ route('admin.platform-emails.create') }}" @class(['active' => request()->routeIs('admin.platform-emails.create')])>+ {{ __('platform_emails.create.button') }}</a>
        <a href="{{ route('admin.platform-emails.branding') }}" @class(['active' => request()->routeIs('admin.platform-emails.branding')])>{{ __('platform_emails.branding') }}</a>
        <a href="{{ route('admin.platform-emails.logs') }}" @class(['active' => request()->routeIs('admin.platform-emails.logs')])>{{ __('platform_emails.logs') }}</a>
    </nav>
</header>
