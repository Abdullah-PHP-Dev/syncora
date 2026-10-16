@extends('layouts.app')

@section('title', __('snapchat_builder.page_title'))

{{-- Snapchat campaign builder - resources/js/components/ads/snapchat/SnapchatCampaignBuilder.vue,
     backed by App\Http\Controllers\Admin\SnapchatAdBuilderController. --}}

@section('content')
    @include('admin.ads.partials.account-switcher', ['platform' => 'snapchat'])

    @if ($account)
        @php
            $builderUrls = [
                'bootstrap' => route('admin.ads.snapchat.builder.bootstrap'),
                'campaigns' => route('admin.ads.snapchat.builder.campaigns'),
                'audiences' => route('admin.ads.snapchat.builder.audiences'),
                'countries' => route('admin.ads.snapchat.builder.countries'),
                'regions' => route('admin.ads.snapchat.builder.regions'),
                'languages' => route('admin.ads.snapchat.builder.languages'),
                'options' => route('admin.ads.snapchat.builder.options', ['type' => 'TYPE']),
                'advancedDemographics' => route('admin.ads.snapchat.builder.advanced_demographics'),
                'interests' => route('admin.ads.snapchat.builder.interests'),
                'osVersions' => route('admin.ads.snapchat.builder.os_versions'),
                'connectionTypes' => route('admin.ads.snapchat.builder.connection_types'),
                'estimate' => route('admin.ads.snapchat.builder.estimate'),
                'bidEstimate' => route('admin.ads.snapchat.builder.bid_estimate'),
                'submit' => route('admin.ads.snapchat.builder.submit'),
                'campaignsList' => route('admin.ads.campaigns.index', ['platform' => 'snapchat']),
                'legacy' => route('admin.ads.campaigns.create', ['platform' => 'snapchat', 'legacy' => 1]),
                'connections' => \App\Support\Connections\HubLink::for('snapchat') ?? route('admin.connections.index'),
            ];
            $galleryUrls = ['index' => route('admin.media-gallery.index'), 'store' => route('admin.media-gallery.store'), 'file' => route('admin.media-gallery.file', ['mediaAsset' => 'MEDIA_ID'])];
            // The shared pickers (SearchPicker, MediaPicker) read their strings from ads_builder.picker.
            $messages = array_merge(__('snapchat_builder'), ['picker' => __('ads_builder.picker')]);
        @endphp
        <snapchat-campaign-builder
            :urls='@json($builderUrls)'
            :gallery-urls='@json($galleryUrls)'
            :account-id="{{ (int) $account->id }}"
            :messages='@json($messages)'
            :media-messages='@json(__('media_gallery'))'
            locale="{{ app()->getLocale() }}"
        ></snapchat-campaign-builder>
    @else
        <div class="card"><div class="card-body text-center py-5">
            <h5>{{ __('snapchat_builder.no_account_title') }}</h5>
            <p class="text-muted">{{ __('snapchat_builder.no_account_text') }}</p>
            <a href="{{ \App\Support\Connections\HubLink::for('snapchat') ?? route('admin.connections.index') }}" class="btn btn-primary">{{ __('snapchat_builder.boot.go_connections') }}</a>
        </div></div>
    @endif
@endsection
