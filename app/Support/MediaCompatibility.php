<?php

namespace App\Support;

/**
 * Upload rules for the Media Gallery, plus a per-platform compatibility
 * check for each stored file.
 *
 * Two layers, deliberately separate:
 *  - ACCEPTED_* / LIMITS: what the gallery accepts at all. Anything outside
 *    these can't reasonably be posted or advertised on ANY supported
 *    platform (wrong container, unsupported codec, absurd size) and is
 *    rejected at upload.
 *  - PLATFORM_RULES: each platform's own published limits. A file can pass
 *    the gallery rules but still need adjusting for one platform (eg. a PNG
 *    for Instagram, which only publishes JPEG via its API, or a 16:9 image
 *    for Snapchat's full-screen 9:16 ads) - that's reported per platform,
 *    never collapsed into a blanket "compatible with everything".
 *
 * Platform limits are conservative summaries of each platform's public API
 * docs (organic publishing and ads combined - the stricter of the two where
 * they differ). They're guidance for the seller; the platform's own API
 * remains the final word at publish time.
 */
class MediaCompatibility
{
    /** Platforms shown on gallery cards - the app's supported posting/ads platforms. */
    public const PLATFORMS = [
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok'    => 'TikTok',
        'snapchat'  => 'Snapchat',
        'x'         => 'X',
        'linkedin'  => 'LinkedIn',
        'youtube'   => 'YouTube',
        'google'    => 'Google',
    ];

    /** extension => allowed server-detected MIME types */
    public const ACCEPTED_IMAGES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
    ];

    public const ACCEPTED_VIDEOS = [
        'mp4'  => ['video/mp4'],
        'm4v'  => ['video/mp4', 'video/x-m4v'],
        'mov'  => ['video/quicktime'],
        'webm' => ['video/webm'],
    ];

    /** Video codecs every major platform can ingest. MPEG-4 Part 2, ProRes, etc. are rejected. */
    public const ACCEPTED_VIDEO_CODECS = ['h264', 'hevc', 'vp8', 'vp9'];

    public const LIMITS = [
        'image_max_bytes'    => 30 * 1024 * 1024,   // 30 MB - Meta/TikTok ads image ceiling
        'video_max_bytes'    => 500 * 1024 * 1024,  // 500 MB - matches AdCampaignRequest's video cap
        'image_min_side'     => 200,
        'image_max_side'     => 12000,
        'video_min_side'     => 240,
        'video_min_seconds'  => 1,
        'video_max_seconds'  => 3600,               // 60 minutes
        'thumbnail_max_bytes' => 5 * 1024 * 1024,
    ];

    /**
     * Per platform: which types it takes and its limits. Ratios are
     * width / height. 'formats' are file extensions (normalised: jpeg->jpg).
     */
    private const PLATFORM_RULES = [
        'facebook' => [
            'image' => ['formats' => ['jpg', 'png', 'gif', 'webp'], 'max_mb' => 30],
            'video' => ['formats' => ['mp4', 'mov', 'm4v'], 'max_mb' => 500, 'min_s' => 1, 'max_s' => 3600],
        ],
        'instagram' => [
            // Content Publishing API: JPEG only, aspect 4:5 - 1.91:1 (1.92 allows 1200x628-style rounding).
            'image' => ['formats' => ['jpg'], 'max_mb' => 8, 'min_ratio' => 0.8, 'max_ratio' => 1.92],
            // Reels: 3s - 15 min, up to 300 MB.
            'video' => ['formats' => ['mp4', 'mov', 'm4v'], 'max_mb' => 300, 'min_s' => 3, 'max_s' => 900, 'codecs' => ['h264', 'hevc']],
        ],
        'tiktok' => [
            // Photo posts: JPEG/WEBP.
            'image' => ['formats' => ['jpg', 'webp'], 'max_mb' => 20],
            'video' => ['formats' => ['mp4', 'mov', 'm4v', 'webm'], 'max_mb' => 500, 'min_s' => 3, 'max_s' => 600, 'min_side' => 360],
        ],
        'snapchat' => [
            // Snap Ads are full-screen vertical: 9:16, at least 1080x1920.
            'image' => ['formats' => ['jpg', 'png'], 'max_mb' => 5, 'min_ratio' => 0.54, 'max_ratio' => 0.59, 'min_width' => 1080],
            'video' => ['formats' => ['mp4', 'mov', 'm4v'], 'max_mb' => 500, 'min_s' => 3, 'max_s' => 180, 'min_ratio' => 0.54, 'max_ratio' => 0.59, 'codecs' => ['h264']],
        ],
        'x' => [
            'image' => ['formats' => ['jpg', 'png', 'gif', 'webp'], 'max_mb' => 5, 'max_mb_gif' => 15],
            'video' => ['formats' => ['mp4', 'mov', 'm4v'], 'max_mb' => 500, 'min_s' => 1, 'max_s' => 140, 'codecs' => ['h264']],
        ],
        'linkedin' => [
            'image' => ['formats' => ['jpg', 'png', 'gif'], 'max_mb' => 8],
            'video' => ['formats' => ['mp4'], 'max_mb' => 500, 'min_s' => 3, 'max_s' => 1800, 'codecs' => ['h264']],
        ],
        'youtube' => [
            'image' => null, // YouTube posts are videos only.
            'video' => ['formats' => ['mp4', 'mov', 'm4v', 'webm'], 'max_mb' => 500, 'min_s' => 1, 'max_s' => 3600],
        ],
        'google' => [
            // Google Business Profile media.
            'image' => ['formats' => ['jpg', 'png'], 'max_mb' => 5, 'min_side' => 250],
            'video' => ['formats' => ['mp4', 'mov', 'm4v'], 'max_mb' => 75, 'min_s' => 1, 'max_s' => 30, 'min_side' => 720],
        ],
    ];

    public static function acceptedExtensions(): array
    {
        return array_keys(self::ACCEPTED_IMAGES + self::ACCEPTED_VIDEOS);
    }

    public static function typeForExtension(string $extension): ?string
    {
        $extension = strtolower($extension);

        return match (true) {
            isset(self::ACCEPTED_IMAGES[$extension]) => 'image',
            isset(self::ACCEPTED_VIDEOS[$extension]) => 'video',
            default => null,
        };
    }

    public static function mimeMatches(string $extension, string $mime): bool
    {
        $extension = strtolower($extension);

        return in_array($mime, (self::ACCEPTED_IMAGES + self::ACCEPTED_VIDEOS)[$extension] ?? [], true);
    }

    /**
     * Normalise getID3's codec identifiers (fourcc / Matroska codec id).
     */
    public static function normaliseCodec(?string $raw): ?string
    {
        $raw = strtolower((string) $raw);

        return match (true) {
            in_array($raw, ['avc1', 'avc3', 'h264', 'x264'], true) => 'h264',
            in_array($raw, ['hvc1', 'hev1', 'hevc', 'h265'], true) => 'hevc',
            in_array($raw, ['v_vp9', 'vp09', 'vp9'], true) => 'vp9',
            in_array($raw, ['v_vp8', 'vp08', 'vp8'], true) => 'vp8',
            $raw === '' => null,
            default => $raw,
        };
    }

    /**
     * @param array{type:string, extension:string, size:int, width:?int, height:?int, duration:?float, video_codec:?string} $media
     * @return array<string, array{ok: bool, issues: string[]}>
     */
    public static function evaluate(array $media): array
    {
        $result = [];

        foreach (array_keys(self::PLATFORMS) as $platform) {
            $result[$platform] = self::evaluateFor($platform, $media);
        }

        return $result;
    }

    private static function evaluateFor(string $platform, array $media): array
    {
        $rules = self::PLATFORM_RULES[$platform][$media['type']] ?? null;
        $name = self::PLATFORMS[$platform];

        if ($rules === null) {
            return ['ok' => false, 'issues' => ["{$name} doesn't accept " . ($media['type'] === 'image' ? 'images' : 'videos') . '.']];
        }

        $issues = [];
        $ext = $media['extension'] === 'jpeg' ? 'jpg' : $media['extension'];
        $mb = $media['size'] / 1024 / 1024;
        $w = (int) ($media['width'] ?? 0);
        $h = (int) ($media['height'] ?? 0);
        $ratio = $w && $h ? $w / $h : null;

        if (!in_array($ext, $rules['formats'], true)) {
            $issues[] = 'Needs ' . self::formatList($rules['formats']) . ' format.';
        }

        $maxMb = $ext === 'gif' && isset($rules['max_mb_gif']) ? $rules['max_mb_gif'] : $rules['max_mb'];
        if ($mb > $maxMb) {
            $issues[] = "Max file size is {$maxMb} MB.";
        }

        if ($ratio !== null && isset($rules['min_ratio']) && ($ratio < $rules['min_ratio'] || $ratio > $rules['max_ratio'])) {
            $issues[] = $rules['max_ratio'] < 1
                ? 'Needs a vertical 9:16 aspect ratio.'
                : 'Aspect ratio must be between 4:5 and 1.91:1.';
        }

        if (isset($rules['min_width']) && $w && $w < $rules['min_width']) {
            $issues[] = "Needs at least {$rules['min_width']}px width.";
        }

        if (isset($rules['min_side']) && $w && $h && min($w, $h) < $rules['min_side']) {
            $issues[] = "Shortest side must be at least {$rules['min_side']}px.";
        }

        if ($media['type'] === 'video') {
            $duration = (float) ($media['duration'] ?? 0);

            if ($duration && $duration < $rules['min_s']) {
                $issues[] = "Must be at least {$rules['min_s']}s long.";
            }

            if ($duration > $rules['max_s']) {
                $issues[] = 'Must be ' . self::humanDuration($rules['max_s']) . ' or shorter.';
            }

            if (isset($rules['codecs']) && $media['video_codec'] && !in_array($media['video_codec'], $rules['codecs'], true)) {
                $issues[] = 'Needs ' . strtoupper(implode('/', $rules['codecs'])) . ' video encoding.';
            }
        }

        return ['ok' => $issues === [], 'issues' => $issues];
    }

    /**
     * Issues are stored in English (canonical text, see evaluate()); this
     * shows them in the current locale (lang/{en,ar}/media_gallery.php
     * issues.*). Unknown text is returned as it is.
     *
     * @param  array<string, array{ok: bool, issues: array<int, string>}>  $compatibility
     */
    public static function localize(?array $compatibility): ?array
    {
        if (! $compatibility || app()->getLocale() === 'en') {
            return $compatibility;
        }

        foreach ($compatibility as $platform => $result) {
            $compatibility[$platform]['issues'] = array_map([self::class, 'localizeIssue'], $result['issues'] ?? []);
        }

        return $compatibility;
    }

    public static function localizeIssue(string $issue): string
    {
        $t = fn (string $key, array $params = []) => __("media_gallery.issues.{$key}", $params);
        $duration = fn (string $v) => match (true) {
            (bool) preg_match('/^(\d+(?:\.\d+)?) min$/', $v, $m) => $t('minutes', ['count' => $m[1]]),
            (bool) preg_match('/^(\d+)s$/', $v, $m) => __('media_gallery.seconds', ['count' => $m[1]]),
            default => $v,
        };

        return match (true) {
            (bool) preg_match('/^Needs (\S+) format\.$/', $issue, $m) => $t('format', ['formats' => $m[1]]),
            (bool) preg_match('/^Max file size is ([\d.]+) MB\.$/', $issue, $m) => $t('max_size', ['mb' => $m[1]]),
            $issue === 'Needs a vertical 9:16 aspect ratio.' => $t('vertical'),
            $issue === 'Aspect ratio must be between 4:5 and 1.91:1.' => $t('ratio'),
            (bool) preg_match('/^Needs at least (\d+)px width\.$/', $issue, $m) => $t('min_width', ['px' => $m[1]]),
            (bool) preg_match('/^Shortest side must be at least (\d+)px\.$/', $issue, $m) => $t('min_side', ['px' => $m[1]]),
            (bool) preg_match('/^Must be at least ([\d.]+)s long\.$/', $issue, $m) => $t('min_length', ['seconds' => $m[1]]),
            (bool) preg_match('/^Must be (.+) or shorter\.$/', $issue, $m) => $t('max_length', ['duration' => $duration($m[1])]),
            (bool) preg_match('/^Needs (\S+) video encoding\.$/', $issue, $m) => $t('codec', ['codecs' => $m[1]]),
            (bool) preg_match("/^(.+) doesn't accept (images|videos)\\.$/", $issue, $m) => $t($m[2] === 'images' ? 'no_images' : 'no_videos', ['platform' => $m[1]]),
            default => $issue,
        };
    }

    private static function formatList(array $formats): string
    {
        return strtoupper(implode('/', $formats));
    }

    private static function humanDuration(int $seconds): string
    {
        return $seconds >= 60 ? ($seconds / 60) . ' min' : "{$seconds}s";
    }
}
