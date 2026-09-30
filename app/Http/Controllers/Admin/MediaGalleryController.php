<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\MediaAssetService;
use App\Support\MediaCompatibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The seller's Media Gallery page plus the JSON endpoints behind it and
 * behind the MediaPicker modal (Content Posting composer + Ads campaign
 * create forms). Everything is scoped to Auth::id(); another user's asset
 * id 404s rather than 403s so ids can't be probed.
 */
class MediaGalleryController extends Controller
{
    public function __construct(private MediaAssetService $media)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'type'     => ['nullable', 'in:image,video'],
            'q'        => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'in:' . implode(',', array_keys(MediaCompatibility::PLATFORMS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort'     => ['nullable', 'in:newest,oldest,largest,name'],
        ]);

        $sort = [
            'newest'  => ['created_at', 'desc'],
            'oldest'  => ['created_at', 'asc'],
            'largest' => ['size', 'desc'],
            'name'    => ['original_name', 'asc'],
        ][$validated['sort'] ?? 'newest'];

        $assets = MediaAsset::ownedBy(Auth::id())
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($validated['q'] ?? null, fn ($q, $term) => $q->where('original_name', 'like', '%' . addcslashes($term, '%_\\') . '%'))
            ->when($validated['platform'] ?? null, fn ($q, $platform) => $q->whereJsonContains('compatible_platforms', $platform))
            ->orderBy(...$sort)
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'media' => $assets]);
        }

        return view('admin.media-gallery.index', [
            'media'     => $assets,
            'platforms' => MediaCompatibility::PLATFORMS,
        ]);
    }

    public function store(Request $request)
    {
        // Shape-only checks here; MediaAssetService::store() does the real
        // content validation (MIME sniffing, dimensions, codec, duration).
        $request->validate([
            'file'      => ['required', 'file'],
            'thumbnail' => ['nullable', 'file'],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.file'     => 'The upload failed - the file may be larger than the server allows.',
        ]);

        $asset = $this->media->store($request->file('file'), Auth::id(), $request->file('thumbnail'));

        return response()->json([
            'success' => true,
            'media'   => $asset,
            'message' => '"' . $asset->original_name . '" uploaded.',
        ]);
    }

    public function destroy(MediaAsset $mediaAsset)
    {
        $this->authorizeOwner($mediaAsset);

        return response()->json(['success' => true, 'message' => $this->media->delete($mediaAsset)]);
    }

    /**
     * Same-origin copy of the file, so the Ads campaign forms can turn a
     * gallery item into a real File for their <input type="file"> - the R2
     * CDN sends no CORS headers, so the browser can't fetch it directly.
     * Registered outside the LaravelLocalization route group (see
     * routes/web.php): its LocaleCookieRedirect middleware calls
     * withCookie() on every response, which a streamed response lacks.
     */
    public function file(Request $request, MediaAsset $mediaAsset)
    {
        $this->authorizeOwner($mediaAsset);

        // ?variant=thumbnail - a video's poster frame (the Facebook video
        // ad form needs one as its own required "thumbnail" file).
        if ($request->query('variant') === 'thumbnail') {
            abort_unless($mediaAsset->thumbnail_path, 404);

            return Storage::disk($mediaAsset->disk)->response($mediaAsset->thumbnail_path, pathinfo($mediaAsset->original_name, PATHINFO_FILENAME) . '-thumbnail.jpg', [
                'Cache-Control' => 'private, max-age=600',
            ]);
        }

        return Storage::disk($mediaAsset->disk)->response($mediaAsset->path, $mediaAsset->original_name, [
            'Content-Type'  => $mediaAsset->mime_type,
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    private function authorizeOwner(MediaAsset $asset): void
    {
        abort_unless($asset->user_id === Auth::id(), 404);
    }
}
