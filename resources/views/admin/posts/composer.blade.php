@extends('layouts.app')

@section('title', 'Create Post')

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
@endpush

@section('content')

    <post-composer
            :accounts='@json($accounts)'
            :categories='@json($categories)'
            :prefill='@json($prefill)'
            store-url="{{ route('admin.posts.store') }}"
            category-store-url="{{ route('admin.categories.store') }}"
            manage-accounts-url="{{ route('admin.connections.index') }}"
            redirect-url="{{ route('admin.posts.dashboard') }}"
            :gallery-urls='@json(['index' => route('admin.media-gallery.index'), 'store' => route('admin.media-gallery.store')])'
            :ai-urls='@json(['content' => route('admin.posts.generate-ai-content'), 'image' => route('admin.posts.generate-ai-image')])'
    ></post-composer>

@stop

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush
