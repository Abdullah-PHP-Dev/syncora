{{--
    A post's media thumbnail - image/video/file branching via
    dash_media_preview() (app/Helpers/Helper.php), falling back to
    <x-platform-icon> when the post has no media at all. Was duplicated
    byte-for-byte between the Recent Posts table row and the Top
    Performing Posts card in admin/posts/dashboard.blade.php, differing
    only by the "lg" size class.

    Props:
      media         nullable App\Models\PostMedia - typically
                    $post->media->first()
      fallbackIcon  string - boxicon suffix shown when there's no media
      fallbackColor string - hex color for the fallback icon, e.g. the
                    platform's brand color
      size          'sm' (default) or 'lg' - matches the existing
                    .dash-list-thumb / .dash-list-thumb-lg sizing
--}}
@props([
    'media',
    'fallbackIcon',
    'fallbackColor' => '#7c5cff',
    'size' => 'sm',
])

@php
    $preview = dash_media_preview($media);
@endphp

<div
    {{ $attributes->class([
        'dash-list-thumb',
        'dash-list-thumb-lg' => $size === 'lg',
        'dash-list-thumb-video' => ($preview['kind'] ?? null) === 'video',
    ]) }}
>
    @if($preview && $preview['kind'] === 'image')
        <img src="{{ $preview['url'] }}" alt="" onerror="this.remove()">
    @elseif($preview && $preview['kind'] === 'video')
        @if($preview['url'])
            <img src="{{ $preview['url'] }}" alt="" onerror="this.remove()">
        @endif
        <span class="dash-media-video-badge"><i class="bx bx-play-circle"></i></span>
    @elseif($preview && $preview['kind'] === 'file')
        <span class="dash-media-file-badge">{{ $preview['ext'] }}</span>
    @else
        <x-platform-icon :icon="$fallbackIcon" :color="$fallbackColor" />
    @endif
</div>
