@extends('layouts.app')

@section('title', __('tiktok_builder.page_title'))

{{-- TikTok campaign builder - resources/js/components/ads/tiktok/TiktokCampaignBuilder.vue,
     backed by App\Http\Controllers\Admin\TiktokAdBuilderController. --}}

@section('content')
    @include('admin.ads.partials.account-switcher', ['platform' => 'tiktok'])

    @if ($account)
        @php
            $builderUrls = [
                'bootstrap' => route('admin.ads.tiktok.builder.bootstrap'),
                'campaigns' => route('admin.ads.tiktok.builder.campaigns'),
                'sparkPosts' => route('admin.ads.tiktok.builder.spark_posts'),
                'pixels' => route('admin.ads.tiktok.builder.pixels'),
                'audiences' => route('admin.ads.tiktok.builder.audiences'),
                'apps' => route('admin.ads.tiktok.builder.apps'),
                'leadForms' => route('admin.ads.tiktok.builder.lead_forms'),
                'locations' => route('admin.ads.tiktok.builder.locations'),
                'languages' => route('admin.ads.tiktok.builder.languages'),
                'interests' => route('admin.ads.tiktok.builder.interests'),
                'deviceModels' => route('admin.ads.tiktok.builder.device_models'),
                'osVersions' => route('admin.ads.tiktok.builder.os_versions'),
                'submit' => route('admin.ads.tiktok.builder.submit'),
                'campaignsList' => route('admin.ads.campaigns.index', ['platform' => 'tiktok']),
                'legacy' => route('admin.ads.campaigns.create', ['platform' => 'tiktok', 'legacy' => 1]),
                'connections' => \App\Support\Connections\HubLink::for('tiktok') ?? route('admin.connections.index'),
            ];
            $galleryUrls = ['index' => route('admin.media-gallery.index'), 'store' => route('admin.media-gallery.store'), 'file' => route('admin.media-gallery.file', ['mediaAsset' => 'MEDIA_ID'])];
            // The shared pickers (SearchPicker, MediaPicker) read their strings from ads_builder.picker.
            $messages = array_merge(__('tiktok_builder'), ['picker' => array_merge(__('ads_builder.picker'), __('tiktok_builder.picker'))]);
        @endphp
        <tiktok-campaign-builder
            :urls='@json($builderUrls)'
            :gallery-urls='@json($galleryUrls)'
            :account-id="{{ (int) $account->id }}"
            :messages='@json($messages)'
            locale="{{ app()->getLocale() }}"
        ></tiktok-campaign-builder>
    @else
        <div class="card"><div class="card-body text-center py-5">
            <h5>{{ __('tiktok_builder.no_account_title') }}</h5>
            <p class="text-muted">{{ __('tiktok_builder.no_account_text') }}</p>
            <a href="{{ \App\Support\Connections\HubLink::for('tiktok') ?? route('admin.connections.index') }}" class="btn btn-primary">{{ __('tiktok_builder.boot.go_connections') }}</a>
        </div></div>
    @endif
@endsection
