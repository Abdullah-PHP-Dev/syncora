@extends('layouts.main')

@section('content')
    <!-- Hero -->
    <section class="position-relative overflow-hidden hero-bg">
        <div class="position-absolute inset-0 grid-bg opacity-75 w-100 h-100"></div>

        <div class="position-relative container pt-5 pb-4 text-center" style="max-width: 960px;">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-white border shadow-sm text-xs fw-bold mb-3">
                <span class="spinner-grow spinner-grow-sm text-purple" role="status"></span>
                {{ __('socialeaz.hero_badge') }}
            </div>

            <h1 class="mt-3 display-3 fw-black tracking-tight">
                {{ __('socialeaz.hero_title_1') }}
                <span class="gradient-text">{{ __('socialeaz.hero_title_2') }}</span>
            </h1>

            <p class="mt-4 fs-5 text-secondary mx-auto" style="max-width: 650px;">
                {{ __('socialeaz.hero_subtitle') }}
            </p>

            <div class="mt-4 d-flex flex-column flex-sm-row align-items-center justify-content-center gap-3">
                <a href="#pricing" class="shine btn btn-dark btn-lg px-4 fw-bold shadow">
                    {{ __('socialeaz.hero_cta') }} <span class="ms-1">→</span>
                </a>
                <a href="#product" class="btn btn-outline-dark btn-lg px-4 fw-bold">
                    {{ __('socialeaz.hero_cta_secondary') }}
                </a>
            </div>

            <div class="mt-3 text-muted small">{{ __('socialeaz.hero_trial_note') }}</div>
        </div>
    </section>
@endsection