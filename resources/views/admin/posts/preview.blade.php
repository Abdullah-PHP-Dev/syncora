@extends('layouts.app')

@section('page_title')
{{ __('Post Preview') }}
@stop

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
@endpush

@section('content')

<post-preview
    :post-id="{{ (int) $postId }}"
    :initial-post='@json($post)'
    :group-posts='@json($groupPosts)'
    platform="{{ $platform }}"
    back-url="{{ url('posts/listing') }}"
    preview-url-template="{{ route('admin.posts.preview', ['post' => '__POST__', 'platform' => '__PLATFORM__']) }}"
    user-name="{{ auth()->user()->name ?? 'Admin' }}"></post-preview>

@stop