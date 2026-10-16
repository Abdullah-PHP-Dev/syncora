<?php

// Posts listing page (admin/posts/listing - PostsDashboard.vue).
return [
    'page_title' => 'Social Media Posts',

    'header' => [
        'badge' => 'Social Media Management',
        'title' => 'Social Media Posts',
        'subtitle' => 'Manage, schedule and monitor all your social media posts from one place.',
        'export' => 'Export',
        'create_post' => 'Create Post',
    ],

    'composer' => [
        'placeholder' => "What's on your mind, :name?",
        'photo' => 'Photo',
        'video' => 'Video',
    ],

    'tabs' => [
        'all' => 'All',
        'posts' => '{0} 0 Posts|{1} 1 Post|[2,*] :count Posts',
    ],

    'toolbar' => [
        'search_placeholder' => 'Search posts by title or content...',
        'all_status' => 'All Status',
        'latest_first' => 'Latest First',
        'oldest_first' => 'Oldest First',
        'grid_view' => 'Grid view',
        'list_view' => 'List view',
    ],

    'status' => [
        'published' => 'Published',
        'scheduled' => 'Scheduled',
        'failed' => 'Failed',
        'pending' => 'Pending',
        'processing' => 'Processing',
        'in_progress' => 'In progress',
        'draft' => 'Draft',
    ],

    'list' => [
        'recent_posts' => 'Recent Posts',
        'available' => '{0} No posts available|{1} 1 Post Available|[2,*] :count Posts Available',
        'empty' => 'No posts found.',
        'actions_for' => 'Actions for :title',
        'post' => 'post',
        'preview' => 'Preview',
        'view_comments' => 'View comments',
        'promote_on' => 'Promote on :platform',
        'showing' => 'Showing :from - :to of :total Posts',
        'previous' => 'Previous page',
        'next' => 'Next page',
    ],

    'quick' => [
        'title' => 'Create post',
        'close' => 'Close',
        'audience' => 'Friends',
        'post_to' => 'Post to',
        'schedule_later' => 'Schedule for later',
        'add_to_post' => 'Add to your post',
        'photo_video' => 'Photo/Video',
        'tag_people' => 'Tag people',
        'feeling' => 'Feeling/activity',
        'location' => 'Location',
        'posting' => 'Posting...',
        'schedule_post' => 'Schedule Post',
        'post' => 'Post',
    ],

    'alerts' => [
        'success' => 'Success!',
        'published' => 'Post published successfully!',
        'scheduled' => 'Post scheduled successfully!',
        'partial' => 'Partial success',
        'error' => 'Error!',
        'generic_error' => 'Something went wrong while posting.',
        'snapchat_note' => 'Snapchat has no auto-publish API - share this post manually instead:',
        'share_snapchat' => 'Share to Snapchat',
    ],
];
