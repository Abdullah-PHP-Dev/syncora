<?php

use App\Support\Database\IndexMigration;

/**
 * Posts and comments: indexes for the per-minute publisher, the workspace
 * posts dashboard/listing, and webhook lookups by the platform's own IDs.
 */
return new class extends IndexMigration
{
    protected array $indexes = [
        // social:publish-posts (every minute) and its stuck-claim sweep: status IN (...) / status = 'publishing'.
        ['posts', 'posts_status_schedule_at_index', ['status', 'schedule_at'], null],
        // Dashboard status counts, the status filter and "upcoming": user_id = ? AND status ... ORDER BY schedule_at.
        ['posts', 'posts_user_status_schedule_at_index', ['user_id', 'status', 'schedule_at'], null],
        // Default listing and date ranges: user_id = ? ORDER BY / BETWEEN created_at. Serves the user_id foreign key.
        ['posts', 'posts_user_created_at_index', ['user_id', 'created_at'], 'posts_user_id_foreign'],
        // Sync and webhooks: social_account_id = ? AND post_id = ?. Serves the social_account_id foreign key.
        ['posts', 'posts_account_post_id_index', ['social_account_id', 'post_id'], 'posts_social_account_id_foreign'],
        // Comment webhooks and sync: platform = ? AND comment_id = ?.
        ['post_comments', 'post_comments_platform_comment_id_index', ['platform', 'comment_id'], null],
        // Notification bell and comment counts: user_id = ? AND read_at IS NULL. Serves the user_id foreign key.
        ['post_comments', 'post_comments_user_read_at_index', ['user_id', 'read_at'], 'post_comments_user_id_foreign'],
    ];
};
