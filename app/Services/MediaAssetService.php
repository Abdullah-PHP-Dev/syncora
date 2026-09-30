<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\PostMedia;
use App\Support\MediaCompatibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Upload / delete for the Media Gallery. Every check here is server-side
 * and reads the file itself (finfo MIME sniffing, getimagesize, getID3) -
 * the browser-supplied extension and Content-Type are never trusted on
 * their own.
 */
class MediaAssetService
{
    private const DISK = 'r2';

    public function store(UploadedFile $file, int $userId, ?UploadedFile $thumbnail = null): MediaAsset
    {
        $meta = $this->inspect($file);

        $base = "uploads/media-gallery/{$userId}/" . now()->format('Y/m') . '/' . Str::uuid();
        $path = "{$base}.{$meta['extension']}";

        $this->put($path, $file);

        $thumbnailPath = null;
        if ($meta['type'] === 'video' && $thumbnail) {
            $thumbnailPath = $this->storeThumbnail($thumbnail, $base);
        }

        $compatibility = MediaCompatibility::evaluate($meta);

        return MediaAsset::create([
            'user_id'              => $userId,
            'type'                 => $meta['type'],
            'disk'                 => self::DISK,
            'path'                 => $path,
            'url'                  => Storage::disk(self::DISK)->url($path),
            'thumbnail_path'       => $thumbnailPath,
            'thumbnail_url'        => $thumbnailPath ? Storage::disk(self::DISK)->url($thumbnailPath) : null,
            'original_name'        => Str::limit($this->cleanName($file->getClientOriginalName()), 250, ''),
            'extension'            => $meta['extension'],
            'mime_type'            => $meta['mime'],
            'size'                 => $meta['size'],
            'width'                => $meta['width'],
            'height'               => $meta['height'],
            'duration'             => $meta['duration'],
            'video_codec'          => $meta['video_codec'],
            'compatibility'        => $compatibility,
            'compatible_platforms' => array_keys(array_filter($compatibility, fn ($r) => $r['ok'])),
        ]);
    }

    /**
     * Returns a message describing what happened. A file still used by a
     * post is only soft-deleted (hidden from the gallery) so the post's
     * media keeps loading; post deletion later removes it for good - see
     * releaseIfOrphaned().
     */
    public function delete(MediaAsset $asset): string
    {
        if ($asset->isReferenced()) {
            $asset->delete();

            return 'Removed from your gallery. The file is kept because a post still uses it.';
        }

        $this->purge($asset);

        return 'Media deleted.';
    }

    /**
     * Called when a post's media is being removed: if the URL belongs to a
     * gallery asset, the gallery decides the file's fate instead of the
     * post - kept while it's in the gallery, purged once it was already
     * removed from the gallery and nothing else references it.
     *
     * @return bool true if the URL belongs to a gallery asset (caller must
     *              not delete the file itself)
     */
    public function releaseIfOrphaned(string $url, ?int $exceptPostMediaId = null): bool
    {
        $asset = MediaAsset::withTrashed()->where('url', $url)->first();

        if (!$asset) {
            return false;
        }

        if ($asset->trashed()) {
            $stillUsed = PostMedia::where(fn ($q) => $q->where('media_asset_id', $asset->id)->orWhere('media_url', $asset->url))
                ->when($exceptPostMediaId, fn ($q) => $q->where('id', '!=', $exceptPostMediaId))
                ->exists();

            if (!$stillUsed) {
                $this->purge($asset);
            }
        }

        return true;
    }

    private function purge(MediaAsset $asset): void
    {
        Storage::disk($asset->disk)->delete(array_filter([$asset->path, $asset->thumbnail_path]));
        $asset->forceDelete();
    }

    /**
     * @return array{type:string, extension:string, mime:string, size:int, width:?int, height:?int, duration:?float, video_codec:?string}
     */
    private function inspect(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            $this->fail('The upload failed - ' . $file->getErrorMessage());
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $type = MediaCompatibility::typeForExtension($extension);

        if (!$type) {
            $this->fail('Unsupported file type ".' . e($extension) . '". Upload JPG, PNG, GIF or WEBP images, or MP4, MOV, M4V or WEBM videos.');
        }

        // finfo sniffs the actual bytes - a renamed PDF/EXE fails here.
        $mime = (string) $file->getMimeType();

        if (!MediaCompatibility::mimeMatches($extension, $mime)) {
            $this->fail("This file's contents ({$mime}) don't match its .{$extension} extension.");
        }

        $size = (int) $file->getSize();
        $maxBytes = MediaCompatibility::LIMITS[$type === 'image' ? 'image_max_bytes' : 'video_max_bytes'];

        if ($size > $maxBytes) {
            $this->fail(ucfirst($type) . 's can be up to ' . ($maxBytes / 1024 / 1024) . ' MB - this file is ' . round($size / 1024 / 1024, 1) . ' MB.');
        }

        if ($size === 0) {
            $this->fail('The file is empty.');
        }

        $meta = ['type' => $type, 'extension' => $extension, 'mime' => $mime, 'size' => $size, 'width' => null, 'height' => null, 'duration' => null, 'video_codec' => null];

        return $type === 'image' ? $this->inspectImage($file, $meta) : $this->inspectVideo($file, $meta);
    }

    private function inspectImage(UploadedFile $file, array $meta): array
    {
        $info = @getimagesize($file->getRealPath());

        if (!$info || !$info[0] || !$info[1]) {
            $this->fail("This image couldn't be read - it may be corrupted.");
        }

        [$w, $h] = $info;
        $min = MediaCompatibility::LIMITS['image_min_side'];
        $max = MediaCompatibility::LIMITS['image_max_side'];

        if (min($w, $h) < $min) {
            $this->fail("Images must be at least {$min}×{$min}px - this one is {$w}×{$h}px.");
        }

        if (max($w, $h) > $max) {
            $this->fail("Images can be at most {$max}px on their longest side - this one is {$w}×{$h}px.");
        }

        return ['width' => $w, 'height' => $h] + $meta;
    }

    private function inspectVideo(UploadedFile $file, array $meta): array
    {
        $info = (new \getID3())->analyze($file->getRealPath());

        if (!empty($info['error']) || empty($info['video'])) {
            $this->fail("This video couldn't be read - it may be corrupted or use an unsupported container.");
        }

        $video = $info['video'];
        $w = (int) ($video['resolution_x'] ?? 0);
        $h = (int) ($video['resolution_y'] ?? 0);

        // Phones record portrait video as landscape frames plus a rotation
        // flag - report the orientation viewers actually see.
        $rotate = (int) ($info['quicktime']['video']['rotate'] ?? $video['rotate'] ?? 0);
        if (in_array(abs($rotate) % 360, [90, 270], true)) {
            [$w, $h] = [$h, $w];
        }

        $codec = MediaCompatibility::normaliseCodec(
            $video['fourcc'] ?? $info['quicktime']['video']['codec_fourcc'] ?? $video['dataformat'] ?? null
        );

        if (!in_array($codec, MediaCompatibility::ACCEPTED_VIDEO_CODECS, true)) {
            $this->fail('Unsupported video encoding' . ($codec ? ' (' . strtoupper(e($codec)) . ')' : '') . '. Export the video as H.264 MP4 - the format every platform accepts.');
        }

        $duration = round((float) ($info['playtime_seconds'] ?? 0), 2);
        $limits = MediaCompatibility::LIMITS;

        if ($duration < $limits['video_min_seconds']) {
            $this->fail('Videos must be at least ' . $limits['video_min_seconds'] . ' second long.');
        }

        if ($duration > $limits['video_max_seconds']) {
            $this->fail('Videos can be at most ' . ($limits['video_max_seconds'] / 60) . ' minutes long.');
        }

        if (!$w || !$h || min($w, $h) < $limits['video_min_side']) {
            $this->fail('Videos must be at least ' . $limits['video_min_side'] . 'p.');
        }

        return ['width' => $w, 'height' => $h, 'duration' => $duration, 'video_codec' => $codec] + $meta;
    }

    /** Poster frame captured in the browser - optional, never fails the upload. */
    private function storeThumbnail(UploadedFile $thumbnail, string $base): ?string
    {
        $mime = (string) $thumbnail->getMimeType();

        if (!$thumbnail->isValid()
            || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $thumbnail->getSize() > MediaCompatibility::LIMITS['thumbnail_max_bytes']
            || !@getimagesize($thumbnail->getRealPath())) {
            return null;
        }

        $path = "{$base}-thumb." . ($mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg'));
        $this->put($path, $thumbnail);

        return $path;
    }

    private function put(string $path, UploadedFile $file): void
    {
        $stream = fopen($file->getRealPath(), 'r');

        try {
            // Streamed, not file_get_contents() - videos can be 500 MB.
            Storage::disk(self::DISK)->put($path, $stream, ['visibility' => 'public', 'ContentType' => $file->getMimeType()]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function cleanName(string $name): string
    {
        // Strip path fragments and control characters; keep unicode (Arabic names).
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename($name)) ?: 'media';

        return trim($name) ?: 'media';
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
