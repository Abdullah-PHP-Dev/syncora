<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A seller's reusable Media Gallery. Unlike post_media / ad_media (one
     * row per post-per-platform / per campaign, each carrying that
     * platform's own upload id), a media_assets row is the user-owned
     * source file, uploaded once to R2 and referenced from wherever it's
     * used:
     *  - post_media.media_asset_id - the composer hands the asset's R2 URL
     *    straight to every platform service (no re-upload), so post_media
     *    rows point at the very same file.
     *  - ad_campaign_media_asset - ad platforms need the bytes uploaded to
     *    their own APIs, so the campaign form submits a copy of the file;
     *    this pivot records which gallery items a campaign was built from.
     *
     * Soft deletes: deleting a gallery item whose file is still referenced
     * by a post only hides it from the gallery (the post's media_url must
     * keep working); unreferenced items are hard-deleted along with their
     * R2 objects. See MediaGalleryController::destroy().
     */
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['image', 'video']);
            $table->string('disk', 20)->default('r2');
            $table->string('path');
            $table->text('url');
            $table->string('thumbnail_path')->nullable();
            $table->text('thumbnail_url')->nullable();
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration', 10, 2)->nullable();
            $table->string('video_codec', 20)->nullable();
            // Per-platform result computed at upload by MediaCompatibility:
            // {"facebook": {"ok": true, "issues": []}, ...}
            $table->json('compatibility')->nullable();
            // Just the platform keys with ok=true, for the gallery's
            // "All Platforms" filter (JSON_CONTAINS).
            $table->json('compatible_platforms')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type', 'deleted_at']);
        });

        Schema::table('post_media', function (Blueprint $table) {
            $table->foreignId('media_asset_id')->nullable()->after('post_id')->constrained('media_assets')->nullOnDelete();
        });

        Schema::create('ad_campaign_media_asset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ad_campaign_id', 'media_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_media_asset');

        Schema::table('post_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_asset_id');
        });

        Schema::dropIfExists('media_assets');
    }
};
