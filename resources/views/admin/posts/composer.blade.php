@extends('layouts.app')

@section('title', 'Create Post')

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
@endpush

@section('content')

    <post-composer
            :accounts='@json($accounts)'
            :categories='@json($categories)'
            store-url="{{ route('admin.posts.store') }}"
            manage-accounts-url="{{ route('admin.posts.create') }}"
            redirect-url="{{ route('admin.posts.dashboard') }}"
    ></post-composer>

@stop

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush
