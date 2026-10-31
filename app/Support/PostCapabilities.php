<?php

namespace App\Support;

use App\Models\Post;

/**
 * What a customer can do with a post, per platform and per post state, so
 * the listing and preview only offer actions the platform's API supports.
 *
 * Verified against the official documentation (October 2026):
 * - Facebook Pages: edit the text of Page posts the app made (POST /{page-post-id}
 *   message), delete (DELETE /{page-post-id}). Media can't be replaced.
 *   developers.facebook.com/docs/pages-api/posts
 * - Instagram: no caption edit (only comment_enabled); DELETE /{ig-media-id}
 *   only through Instagram API with Facebook Login (instagram_manage_contents),
 *   not for accounts connected with Instagram Login.
 *   developers.facebook.com/docs/instagram-platform/reference/instagram-media
 * - Threads: no edit; DELETE /{threads-media-id} (threads_delete, 100/day).
 *   developers.facebook.com/docs/threads/posts/delete-posts
 * - Google Business Profile: localPosts.patch (updateMask=summary) and delete.
 *   developers.google.com/my-business/reference/rest/v4/accounts.locations.localPosts
 * - YouTube: videos.update (title, description; tags and category kept) and
 *   videos.delete. developers.google.com/youtube/v3/docs/videos/update
 * - LinkedIn: PARTIAL_UPDATE of commentary, DELETE /rest/posts/{urn}.
 *   learn.microsoft.com/linkedin/marketing/community-management/shares/posts-api
 * - X: DELETE /2/tweets/{id}; the API has no edit.  docs.x.com/x-api/posts/delete-post
 * - Pinterest: DELETE /v5/pins/{id}; PATCH /v5/pins/{id} is in beta and not
 *   available to every app, so it isn't offered.  developers.pinterest.com/docs/api/v5
 * - TikTok: the Content Posting API creates posts only - no edit or delete.
 *   developers.tiktok.com/doc/content-posting-api-get-started
 * - WhatsApp: a "post" is a template broadcast; delivered messages can't be
 *   recalled, so only SocialEaz's record is removed ('local').
 */
class PostCapabilities
{
    /** Published-post operations each platform's API supports. */
    public const PLATFORMS = [
        'facebook' => ['edit' => ['content'], 'delete' => true],
        'instagram' => ['edit' => [], 'delete' => 'facebook_login'],
        'threads' => ['edit' => [], 'delete' => true],
        'google' => ['edit' => ['content'], 'delete' => true],
        'youtube' => ['edit' => ['title', 'content'], 'delete' => true],
        'linkedin' => ['edit' => ['content'], 'delete' => true],
        'x' => ['edit' => [], 'delete' => true],
        'pinterest' => ['edit' => [], 'delete' => true],
        'tiktok' => ['edit' => [], 'delete' => false],
        'whatsapp' => ['edit' => [], 'delete' => 'local'],
    ];

    /**
     * Permissions a delete needs beyond posting. SocialEaz's connect flows
     * don't request them (Meta reviews each one), so the action is offered
     * only when the account's connection actually granted it.
     */
    public const DELETE_SCOPES = [
        'instagram' => 'instagram_manage_contents',
        'threads' => 'threads_delete',
    ];

    /** Fields a not-yet-published post can change in SocialEaz before it goes out. */
    public const LOCAL_EDIT_FIELDS = ['title', 'content'];

    /** published | scheduled | pending | processing | draft | failed */
    public static function state(Post $post): string
    {
        $status = strtolower((string) $post->status);

        return match (true) {
            in_array($status, ['completed', 'published'], true) => 'published',
            $status === 'failed' => 'failed',
            $status === 'draft' => 'draft',
            // 'publishing' is the publisher's claim while it sends the post (PublishPosts).
            in_array($status, ['processing', 'in_progress', 'publishing'], true) => 'processing',
            // A pending post with a future time is waiting for its schedule.
            $post->schedule_mode && $post->schedule_at && $post->schedule_at->isFuture() => 'scheduled',
            default => 'pending',
        };
    }

    /**
     * @return array{state: string, can_edit: bool, edit_fields: string[], edit_remote: bool,
     *               can_delete: bool, delete_remote: bool, reason: ?string}
     *
     * reason is a posts_listing.caps.* key explaining a missing action.
     */
    public static function for(Post $post): array
    {
        $state = self::state($post);
        $caps = ['state' => $state, 'can_edit' => false, 'edit_fields' => [], 'edit_remote' => false, 'can_delete' => false, 'delete_remote' => false, 'reason' => null];

        // Not on the platform yet: everything is SocialEaz's own record.
        if ($state === 'scheduled') {
            return ['can_edit' => true, 'edit_fields' => [...self::LOCAL_EDIT_FIELDS, 'schedule_at'], 'can_delete' => true] + $caps;
        }
        if ($state === 'draft') {
            return ['can_edit' => true, 'edit_fields' => self::LOCAL_EDIT_FIELDS, 'can_delete' => true] + $caps;
        }
        if ($state === 'failed') {
            return ['can_delete' => true] + $caps;
        }
        // Being published right now - changing it would race the publisher.
        if (in_array($state, ['pending', 'processing'], true)) {
            return ['reason' => 'in_progress'] + $caps;
        }

        $platform = self::PLATFORMS[$post->platform] ?? ['edit' => [], 'delete' => false];
        if (empty($post->post_id)) {
            return ['reason' => 'no_platform_id'] + $caps;
        }

        if ($platform['delete'] === 'local') {
            return ['can_delete' => true, 'reason' => 'local_only'] + $caps;
        }

        $delete = $platform['delete'] === 'facebook_login'
            ? (($post->socialAccount?->metadata['settings']['auth_type'] ?? null) !== 'instagram_login')
            : (bool) $platform['delete'];
        $missingScope = $delete && isset(self::DELETE_SCOPES[$post->platform])
            && ! in_array(self::DELETE_SCOPES[$post->platform], (array) ($post->socialAccount?->connection?->granted_scopes ?? []), true);
        if ($missingScope) {
            $delete = false;
        }

        return [
            'can_edit' => $platform['edit'] !== [],
            'edit_fields' => $platform['edit'],
            'edit_remote' => $platform['edit'] !== [],
            'can_delete' => $delete,
            'delete_remote' => $delete,
            'reason' => match (true) {
                $delete && $platform['edit'] !== [] => null,
                $missingScope => 'missing_permission',
                $platform['delete'] === 'facebook_login' && ! $delete => 'instagram_login',
                $delete => 'no_edit',
                default => 'platform_read_only',
            },
        ] + $caps;
    }
}
