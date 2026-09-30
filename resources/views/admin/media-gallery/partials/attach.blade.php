{{--
    "Media Gallery" / "Upload New" for the ads campaign forms. Copies the
    chosen gallery files into the form's own file input (default #mediaInput)
    - see MediaInputAttach.vue. Usage:
        @include('admin.media-gallery.partials.attach', ['platform' => 'snapchat'])
        @include('admin.media-gallery.partials.attach', ['platform' => 'facebook', 'thumbnailTarget' => '#thumbnailInput'])
--}}
@php
    $mediaAttachUrls = [
        'index' => route('admin.media-gallery.index'),
        'store' => route('admin.media-gallery.store'),
        'file'  => route('admin.media-gallery.file', ['mediaAsset' => 'MEDIA_ID']),
    ];
@endphp
<media-input-attach
    platform="{{ $platform ?? '' }}"
    target="{{ $target ?? '#mediaInput' }}"
    thumbnail-target="{{ $thumbnailTarget ?? '' }}"
    :urls='@json($mediaAttachUrls)'
></media-input-attach>
