@extends('layouts.app')

@section('title', __('admin.marketing_tools.ads.header'))

@section('content')

    {{-- Every ad platform's OAuth callback (see the *AdService::callback()
         methods) redirects back here with a flash message on both success
         and failure. --}}
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <ads-dashboard :data='@json($data)'></ads-dashboard>

    @php
        // Shared with posts/dashboard.blade.php via the social-connect-modal
        // Blade component. The Vue dashboard's "Connect account" buttons all
        // target #socialConnectModal.
        $adsConnectPlatforms = collect([
            'facebook'  => 'bxl-facebook',
            'instagram' => 'bxl-instagram',
            'x'         => 'bxl-twitter',
            'snapchat'  => 'bxl-snapchat',
            'tiktok'    => 'bxl-tiktok',
            'google'    => 'bxl-google',
            'youtube'   => 'bxl-youtube',
            'linkedin'  => 'bxl-linkedin',
        ])->map(function ($icon, $platform) use ($connected) {
            $isConnected = ($connected[$platform] ?? 0) == 1;

            return [
                'key'       => $platform,
                'class'     => $platform === 'x' ? 'twitter' : $platform,
                'icon'      => $icon,
                'label'     => __("admin.marketing_tools.ads.accounts.{$platform}.header"),
                'url'       => $isConnected
                    ? route('admin.ads.campaigns.index', ['platform' => $platform])
                    : route('admin.ads.redirect', $platform),
                'connected' => $isConnected,
            ];
        })->values()->all();
    @endphp

    <x-social-connect-modal id="socialConnectModal" :platforms="$adsConnectPlatforms" />

@endsection
