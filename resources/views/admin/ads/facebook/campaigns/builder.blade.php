@extends('layouts.app')

@section('title', 'Create Facebook & Instagram ad')

{{-- Facebook campaign builder - resources/js/components/ads/facebook/FacebookCampaignBuilder.vue,
     backed by App\Http\Controllers\Admin\FacebookAdBuilderController. --}}

@section('content')
    @include('admin.ads.partials.account-switcher')

    @if ($account)
        @php
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
                'locations' => route('admin.ads.facebook.builder.locations'),
                'locales' => route('admin.ads.facebook.builder.locales'),
                'estimate' => route('admin.ads.facebook.builder.estimate'),
                'submit' => route('admin.ads.facebook.builder.submit'),
                'campaignsList' => route('admin.ads.campaigns.index', ['platform' => 'facebook']),
                'connections' => \App\Support\Connections\HubLink::for('facebook') ?? route('admin.connections.index'),
            ];
            $galleryUrls = ['index' => route('admin.media-gallery.index'), 'store' => route('admin.media-gallery.store'), 'file' => route('admin.media-gallery.file', ['mediaAsset' => 'MEDIA_ID'])];
        @endphp
        <facebook-campaign-builder
            :urls='@json($builderUrls)'
            :gallery-urls='@json($galleryUrls)'
            :account-id="{{ (int) $account->id }}"
        ></facebook-campaign-builder>
    @else
        <div class="card"><div class="card-body text-center py-5">
            <h5>Connect a Facebook ad account first</h5>
            <p class="text-muted">Connect Meta in Connections, then come back to create your ad.</p>
            <a href="{{ \App\Support\Connections\HubLink::for('facebook') ?? route('admin.connections.index') }}" class="btn btn-primary">Go to Connections</a>
        </div></div>
    @endif
@endsection
