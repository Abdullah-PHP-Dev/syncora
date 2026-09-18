@extends('layouts.app')

@section('title', 'New Campaign')

@push('styles')
@include('layouts.partials.dash-styles')
@endpush

@section('content')
<div class="socialeaz-dash">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('admin.email.campaigns.index') }}" class="dash-link"><i class="bx bx-arrow-back"></i> Campaigns</a>
            <h4 class="dash-title mb-0">New Campaign</h4>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2"><i class="bx bx-error-circle fs-5"></i> {{ session('error') }}</div>
    @endif

    <form action="{{ route('admin.email.campaigns.store') }}" method="POST">
        @csrf
        @include('admin.email.campaigns._form', ['campaign' => null])
    </form>

</div>
@endsection
