@extends('layouts.app')

@section('title', 'Create New Campaign')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
@endpush

@section('content')
    <facebook-campaign-builder></facebook-campaign-builder>
@endsection

@push('scripts')
    <script>
        // Server-rendered data + route URLs for the Vue campaign builder.
        // Pushed to @stack('scripts') (which renders outside the Vue-2 #app
        // root and as a classic inline script runs before Vite's deferred
        // app.js mounts the component), so the component's data() can read
        // them without large inline-JSON props on an in-DOM element.
        window.__ROUTES__ = {
            campaignsIndex: @json(route('admin.ads.campaigns.index', ['platform' => 'facebook'])),
        };
        window.__BUILDER_DATA__ = {
            countries: @json($countriesData),
            pages: @json($pagesData),
            instagramAccount: @json($instagramAccountData),
            existingCampaigns: @json($existingCampaigns),
            existingAdsets: @json($existingAdsets),
            hasAdAccount: {{ $account ? 'true' : 'false' }},
            currency: @json($account->currency ?? 'USD'),
            storeUrl: @json(route('admin.ads.campaigns.store', ['platform' => 'facebook'])),
            indexUrl: @json(route('admin.ads.campaigns.index', ['platform' => 'facebook'])),
        };
    </script>
@endpush
