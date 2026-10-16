@extends('layouts.app')

@section('title', __('x_builder.page_title'))

{{-- X campaign builder - resources/js/components/ads/x/XCampaignBuilder.vue,
     backed by App\Http\Controllers\Admin\XAdBuilderController. --}}

@section('content')
    @include('admin.ads.partials.account-switcher', ['platform' => 'x'])

    @if ($account)
        @php
            $builderUrls = [
                'bootstrap' => route('admin.ads.x.builder.bootstrap'),
                'campaigns' => route('admin.ads.x.builder.campaigns'),
                'tweets' => route('admin.ads.x.builder.tweets'),
                'audiences' => route('admin.ads.x.builder.audiences'),
                'locations' => route('admin.ads.x.builder.locations'),
                'languages' => route('admin.ads.x.builder.languages'),
                'interests' => route('admin.ads.x.builder.interests'),
                'conversations' => route('admin.ads.x.builder.conversations'),
                'events' => route('admin.ads.x.builder.events'),
                'platforms' => route('admin.ads.x.builder.platforms'),
                'devices' => route('admin.ads.x.builder.devices'),
                'networkOperators' => route('admin.ads.x.builder.network_operators'),
                'appCategories' => route('admin.ads.x.builder.app_categories'),
                'users' => route('admin.ads.x.builder.users'),
                'estimate' => route('admin.ads.x.builder.estimate'),
                'submit' => route('admin.ads.x.builder.submit'),
                'campaignsList' => route('admin.ads.campaigns.index', ['platform' => 'x']),
                'legacy' => route('admin.ads.campaigns.create', ['platform' => 'x', 'legacy' => 1]),
                'connections' => \App\Support\Connections\HubLink::for('x') ?? route('admin.connections.index'),
            ];
            $galleryUrls = ['index' => route('admin.media-gallery.index'), 'store' => route('admin.media-gallery.store'), 'file' => route('admin.media-gallery.file', ['mediaAsset' => 'MEDIA_ID'])];
            // The shared pickers (SearchPicker, MediaPicker) read their strings from ads_builder.picker.
            $messages = array_merge(__('x_builder'), ['picker' => __('ads_builder.picker')]);
        @endphp
        <xads-campaign-builder
            :urls='@json($builderUrls)'
            :gallery-urls='@json($galleryUrls)'
            :account-id="{{ (int) $account->id }}"
            :messages='@json($messages)'
            :media-messages='@json(__('media_gallery'))'
            locale="{{ app()->getLocale() }}"
        ></xads-campaign-builder>
    @else
        <div class="card"><div class="card-body text-center py-5">
            <h5>{{ __('x_builder.no_account_title') }}</h5>
            <p class="text-muted">{{ __('x_builder.no_account_text') }}</p>
            <a href="{{ \App\Support\Connections\HubLink::for('x') ?? route('admin.connections.index') }}" class="btn btn-primary">{{ __('x_builder.boot.go_connections') }}</a>
        </div></div>
    @endif
@endsection
