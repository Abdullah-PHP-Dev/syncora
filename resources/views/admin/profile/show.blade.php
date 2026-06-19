@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">

            <div class="card px-sm-6 px-0">
                <div class="card-body">

                    <!-- Logo -->
                    <div class="app-brand justify-content-center mb-4">
                        <a href="{{ url('/') }}" class="app-brand-link gap-2">
                           <span class="app-brand-text demo text-heading fw-bold">
                                {{ config('app.name') }}
                            </span>
                        </a>
                    </div>

                    <h4 class="mb-1">{{ __('admin.profile.my_profile') }}</h4>
                    <p class="mb-4">{{ __('admin.profile.profile_description') }}</p>

                    <form id="profile" method="POST">
                        @csrf

                        <!-- Name -->
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.profile.name') }}<span class="error-message">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{Auth::user()->name}}">
                            <p class="error-message error-name"></p>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.profile.email') }}<span class="error-message">*</span></label>
                            <input type="email" name="email" class="form-control"  value="{{Auth::user()->email}}">
                            <p class="error-message error-email"></p>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.profile.password') }}</label>
                            <input type="password" name="password" class="form-control">
                            <p class="error-message error-password"></p>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-3">
                            <label class="form-label">{{ __('admin.profile.confirm_password') }}</label>
                            <input type="password" name="password_confirmation" class="form-control">
                            <p class="error-message error-password_confirmation"></p>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            {{ __('admin.profile.update_profile') }}
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        var profile = @json(Auth::user()->id);
        var saveDescription = "{{__('admin.sweet-alert.save-description')}}";
        var saveHeader = "{{__('admin.sweet-alert.save-header')}}";
        var saveHeader = "{{__('admin.sweet-alert.save-header')}}";
        var dontSave = "{{__('admin.sweet-alert.dont-save')}}";
        var wentWrong = "{{__('admin.sweet-alert.went-wrong')}}";
        var error = "{{__('admin.sweet-alert.error')}}";
        var success = "{{__('admin.sweet-alert.success')}}";
        var changesNotSaved = "{{__('admin.sweet-alert.changes-not-saved')}}";
        var updateUrl = "{{ route('admin.profiles.update', ['profile' => ':profile']) }}";

        updateUrl = updateUrl.replace(':profile', profile);
    
    </script>
    <script src="{{ asset('assets/js/dashboards-analytics.js') }}"></script>
    <script src="{{ asset('assets/js/admin/profile.js') }}"></script>
@endpush