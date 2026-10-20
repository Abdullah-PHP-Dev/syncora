@extends('layouts.app')

@section('title', isset($edit) ? __('ads_builder.edit.page_title') : (($platform ?? 'facebook') === 'instagram' ? __('ads_builder.page_title_instagram') : __('ads_builder.page_title')))

{{-- Facebook campaign builder - resources/js/components/ads/facebook/FacebookCampaignBuilder.vue,
     backed by App\Http\Controllers\Admin\FacebookAdBuilderController. --}}

@section('content')
    {{-- Instagram ads run on the Meta (Facebook) ad account. --}}
    @include('admin.ads.partials.account-switcher', ['platform' => 'facebook'])

    @if ($account)
        @php
            // Facebook or Instagram page - same builder, same Meta ad account.
            $channel = ($platform ?? 'facebook') === 'instagram' ? 'instagram' : 'facebook';
            $builderUrls = [
                'bootstrap' => route('admin.ads.facebook.builder.bootstrap'),
                'campaigns' => route('admin.ads.facebook.builder.campaigns'),
                'adsets' => route('admin.ads.facebook.builder.adsets', ['campaign' => 'CAMPAIGN_ID']),
                'creatives' => route('admin.ads.facebook.builder.creatives'),
                'creativePreview' => route('admin.ads.facebook.builder.creative_preview', ['creative' => 'CREATIVE_ID']),
                'audiences' => route('admin.ads.facebook.builder.audiences'),
                'pixels' => route('admin.ads.facebook.builder.pixels'),
                'apps' => route('admin.ads.facebook.builder.apps'),
                'instagram' => route('admin.ads.facebook.builder.instagram_accounts'),
                'leadForms' => route('admin.ads.facebook.builder.lead_forms', ['page' => 'PAGE_ID']),
                'targeting' => route('admin.ads.facebook.builder.targeting'),
                'work' => route('admin.ads.facebook.builder.work'),
                'locations' => route('admin.ads.facebook.builder.locations'),
                'locales' => route('admin.ads.facebook.builder.locales'),
                'estimate' => route('admin.ads.facebook.builder.estimate'),
                'submit' => route('admin.ads.facebook.builder.submit'),
                'update' => isset($edit) ? route('admin.ads.facebook.builder.update', ['campaign' => $edit['id']]) : null,
                'campaignsList' => route('admin.ads.campaigns.index', ['platform' => $channel]),
                'connections' => \App\Support\Connections\HubLink::for('facebook') ?? route('admin.connections.index'),
            ];
            $galleryUrls = ['index' => route('admin.media-gallery.index'), 'store' => route('admin.media-gallery.store'), 'file' => route('admin.media-gallery.file', ['mediaAsset' => 'MEDIA_ID'])];
        @endphp
        <facebook-campaign-builder
            :urls='@json($builderUrls)'
            :gallery-urls='@json($galleryUrls)'
            :account-id="{{ (int) $account->id }}"
            :messages='@json(__('ads_builder'))'
            locale="{{ app()->getLocale() }}"
            channel="{{ $channel }}"
            :edit='@json($edit ?? null)'
        ></facebook-campaign-builder>
    @else
        <div class="card"><div class="card-body text-center py-5">
            <h5>{{ __('ads_builder.no_account_title') }}</h5>
            <p class="text-muted">{{ __('ads_builder.no_account_text') }}</p>
            <a href="{{ \App\Support\Connections\HubLink::for('facebook') ?? route('admin.connections.index') }}" class="btn btn-primary">{{ __('ads_builder.boot.go_connections') }}</a>
        </div></div>
    @endif
@endsection
