@extends('layouts.app')

@section('title', __('admin.sidebar.media_gallery'))

@section('content')

    @php
        // route()-generated so every URL carries the locale prefix; the
        // file stream is deliberately non-localized (see routes/web.php).
        $mediaUrls = [
            'index'   => route('admin.media-gallery.index'),
            'store'   => route('admin.media-gallery.store'),
            'destroy' => route('admin.media-gallery.destroy', ['mediaAsset' => 'MEDIA_ID']),
            'file'    => route('admin.media-gallery.file', ['mediaAsset' => 'MEDIA_ID']),
        ];
    @endphp

    <media-gallery
        :initial-media='@json($media)'
        :urls='@json($mediaUrls)'
    ></media-gallery>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush
