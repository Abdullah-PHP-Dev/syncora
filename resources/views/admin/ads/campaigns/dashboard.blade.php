@extends('layouts.app')

@section('title', __('admin.marketing_tools.ads.campaign.header'))

@section('content')

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

    <platform-campaigns-dashboard
        :data='@json($data)'
        csrf="{{ csrf_token() }}"
    ></platform-campaigns-dashboard>

@endsection
