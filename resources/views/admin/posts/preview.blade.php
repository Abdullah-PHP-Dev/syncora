@extends('layouts.app')

@section('page_title')
    {{ __('Post Preview') }}
@stop

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
@endpush

@section('content')

    <post-preview
            :post-id="{{ (int) $postId }}"
            platform="{{ $platform }}"
            back-url="{{ url('admin/posts') }}"
            user-name="{{ auth()->user()->name ?? 'Admin' }}"
    ></post-preview>

@stop
