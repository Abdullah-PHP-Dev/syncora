<?php

use App\Support\Settings;


function lang()
{
	return LaravelLocalization::getCurrentLocale();
}


if (! function_exists('adminSetting')) {

    /**
     * Get setting value.
     *
     * Reads from App\Support\Settings, which caches the whole admin_settings
     * table (see that class for the caching strategy and the JSON-decode
     * heuristic this depends on - the docblock there covers the Slack
     * client_id precision bug that heuristic exists for).
     */
    function adminSetting(string $key, $default = null)
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('set_adminSetting')) {

    /**
     * Create or update setting. Invalidates the cached settings (see
     * App\Support\Settings) so the change is visible immediately.
     */
    function set_adminSetting(string $key, $value): bool
    {
        return Settings::set($key, $value);
    }
}

if (! function_exists('deleteAdminSetting')) {

    /**
     * Delete setting. Invalidates the cached settings (see
     * App\Support\Settings) so the change is visible immediately.
     */
    function deleteAdminSetting(string $key): bool
    {
        return Settings::delete($key);
    }
}

if (! function_exists('dash_short')) {

    /**
     * Short display form for large counters (12400 -> "12.4K"), used
     * throughout the posts/ads dashboards' stat cards - matches the
     * target design without pretending precision the data doesn't have.
     */
    function dash_short($n): string
    {
        $n = (float) $n;

        if ($n >= 1000000) {
            return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M';
        }

        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
        }

        return number_format($n);
    }
}

if (! function_exists('dash_media_preview')) {

    /**
     * Normalizes a PostMedia row into a renderable preview descriptor.
     *
     * PostMedia::media_type is one of 'image' | 'gif' | 'video' | 'file'
     * (the catch-all the upload services use for everything else - xlsx,
     * pdf, docx, zip, ...). Videos never get a thumbnail_url generated
     * (upload services leave it null - see
     * MetaPostService::uploadMediaToS3()), and "file" uploads obviously
     * aren't renderable as <img> either, so every spot that shows a
     * post's media needs to branch on this rather than assuming
     * media_url is always an image.
     */
    function dash_media_preview($media): ?array
    {
        if (! $media) {
            return null;
        }

        $ext = strtoupper(pathinfo($media->media_url ?? '', PATHINFO_EXTENSION));

        return match ($media->media_type ?? 'file') {
            'image', 'gif' => ['kind' => 'image', 'url' => $media->media_url],
            'video' => ['kind' => 'video', 'url' => $media->thumbnail_url, 'ext' => $ext],
            default => ['kind' => 'file', 'ext' => $ext ?: 'FILE'],
        };
    }
}
