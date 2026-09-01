<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title }}</title>

    {{-- Server-side-fetched by share-button crawlers (Snap's Creative Kit,
         and any other og:-reading share button) - no session cookie is
         sent with that fetch, which is why this page lives outside the
         auth middleware group (see routes/web.php). --}}
    <meta property="og:site_name" content="{{ config('app.name', 'SocialEaz') }}" />
    <meta property="og:title" content="{{ $title }}" />
    @if($description)
    <meta property="og:description" content="{{ $description }}" />
    @endif
    @if($media && in_array($media->media_type, ['image', 'gif']))
    <meta property="og:image" content="{{ $media->media_url }}" />
    <meta name="twitter:card" content="summary_large_image" />
    @elseif($media && $media->media_type === 'video' && $media->thumbnail_url)
    <meta property="og:image" content="{{ $media->thumbnail_url }}" />
    <meta property="og:video" content="{{ $media->media_url }}" />
    @endif

    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0f14;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .share-card {
            max-width: 420px;
            width: 100%;
            background: #1a1a22;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
        }
        .share-media { width: 100%; display: block; background: #000; max-height: 460px; object-fit: cover; }
        .share-body { padding: 18px 20px; }
        .share-title { font-size: 15px; font-weight: 600; margin: 0 0 6px; }
        .share-desc { font-size: 13px; color: #a1a1aa; margin: 0; white-space: pre-wrap; }
        .share-brand { font-size: 11px; color: #6b6b76; margin-top: 14px; text-transform: uppercase; letter-spacing: .05em; }
    </style>
</head>
<body>

    <div class="share-card">
        @if($media && in_array($media->media_type, ['image', 'gif']))
            <img class="share-media" src="{{ $media->media_url }}" alt="">
        @elseif($media && $media->media_type === 'video')
            <video class="share-media" src="{{ $media->media_url }}" controls poster="{{ $media->thumbnail_url }}"></video>
        @endif

        <div class="share-body">
            <p class="share-title">{{ $title }}</p>
            @if($description)
            <p class="share-desc">{{ $description }}</p>
            @endif
            <div class="share-brand">{{ config('app.name', 'SocialEaz') }}</div>
        </div>
    </div>

</body>
</html>
