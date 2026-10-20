@extends('layouts.app')

{{-- Google campaign builder - resources/js/components/ads/google/GoogleCampaignBuilder.vue,
     backed by App\Http\Controllers\Admin\GoogleAdBuilderController. The YouTube page
     ($platform === 'youtube') is the same builder in YouTube mode (Demand Gen on the
     same Google Ads account). --}}
@php
    $youtube = ($platform ?? 'google') === 'youtube';
@endphp

@section('title', isset($edit) ? __('google_builder.edit.page_title') : ($youtube ? __('google_builder.youtube.page_title') : __('google_builder.page_title')))

@section('content')
    @include('admin.ads.partials.account-switcher', ['platform' => $youtube ? 'youtube' : 'google'])

    @if ($account)
        @php
            $builderUrls = [
                'bootstrap' => route('admin.ads.google.builder.bootstrap'),
                'locations' => route('admin.ads.google.builder.locations'),
                'languages' => route('admin.ads.google.builder.languages'),
                'audiences' => route('admin.ads.google.builder.audiences'),
                'interests' => route('admin.ads.google.builder.interests'),
                'topics' => route('admin.ads.google.builder.topics'),
                'options' => route('admin.ads.google.builder.options', ['type' => 'TYPE']),
                'keywordIdeas' => route('admin.ads.google.builder.keyword_ideas'),
                'submit' => route('admin.ads.google.builder.submit'),
                'update' => isset($edit) ? route('admin.ads.google.builder.update', ['campaign' => $edit['id']]) : null,
                'campaignsList' => route('admin.ads.campaigns.index', ['platform' => $youtube ? 'youtube' : 'google']),
                'youtube' => route('admin.ads.campaigns.create', ['platform' => 'youtube']),
                'google' => route('admin.ads.campaigns.create', ['platform' => 'google']),
                'legacy' => route('admin.ads.campaigns.create', ['platform' => $youtube ? 'youtube' : 'google', 'legacy' => 1]),
                'connections' => \App\Support\Connections\HubLink::for('google') ?? route('admin.connections.index'),
            ];
            $galleryUrls = ['index' => route('admin.media-gallery.index'), 'store' => route('admin.media-gallery.store'), 'file' => route('admin.media-gallery.file', ['mediaAsset' => 'MEDIA_ID'])];
            // The shared pickers (SearchPicker, MediaPicker) read their strings from ads_builder.picker, BudgetSummary from ads_builder.budget_summary.
            $messages = array_merge(__('google_builder'), ['picker' => __('ads_builder.picker'), 'budget_summary' => __('ads_builder.budget_summary')]);
        @endphp
        <google-campaign-builder
            :urls='@json($builderUrls)'
            :gallery-urls='@json($galleryUrls)'
            :account-id="{{ (int) $account->id }}"
            :messages='@json($messages)'
            :media-messages='@json(__('media_gallery'))'
            mode="{{ $youtube ? 'youtube' : 'google' }}"
            :edit='@json($edit ?? null)'
            locale="{{ app()->getLocale() }}"
        ></google-campaign-builder>
    @else
        <div class="card"><div class="card-body text-center py-5">
            <h5>{{ __('google_builder.no_account_title') }}</h5>
            <p class="text-muted">{{ $youtube ? __('google_builder.youtube.no_account_text') : __('google_builder.no_account_text') }}</p>
            <a href="{{ \App\Support\Connections\HubLink::for('google') ?? route('admin.connections.index') }}" class="btn btn-primary">{{ __('google_builder.boot.go_connections') }}</a>
        </div></div>
    @endif
@endsection
