@extends('layouts.app')

@section('title', __('admin.marketing_tools.ads.header'))

@push('styles')
    <style>
        [v-cloak] { display: none !important; }
    </style>
@endpush

@section('content')

    <div v-cloak>
        <ads-dashboard :data='@json($data)'>
            <x-connect-social-media id="adsConnectSocialMediaModal" />
        </ads-dashboard>
    </div>

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
                    : (\App\Support\Connections\HubLink::for($platform) ?? route('admin.ads.redirect', $platform)),
                'connected' => $isConnected,
                // Meta connects once in the Connection Hub.
                'note'      => \App\Support\Connections\HubLink::managed($platform) ? \App\Support\Connections\HubLink::note() : null,
            ];
        })->values()->all();
    @endphp

    <x-social-connect-modal id="socialConnectModal" :platforms="$adsConnectPlatforms" />

@endsection
