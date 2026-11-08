<?php

// Rules for the common post format supported by MCP. Delivery uses the native
// website publishers. WhatsApp template broadcasts are a separate messaging flow.
return [
    'platforms' => [
        'facebook' => ['label' => 'Facebook', 'media_required' => false, 'text_required' => false, 'title_required' => false, 'max_images' => 10, 'max_videos' => 1, 'image_extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'video_extensions' => ['mp4', 'mov', 'm4v']],
        'instagram' => ['label' => 'Instagram', 'media_required' => true, 'text_required' => false, 'title_required' => false, 'max_images' => 10, 'max_videos' => 1, 'image_extensions' => ['jpg', 'jpeg'], 'video_extensions' => ['mp4', 'mov', 'm4v']],
        'x' => ['label' => 'X', 'media_required' => false, 'text_required' => false, 'title_required' => false, 'max_images' => 4, 'max_videos' => 1, 'image_extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'video_extensions' => ['mp4', 'mov', 'm4v']],
        'linkedin' => ['label' => 'LinkedIn', 'media_required' => false, 'text_required' => false, 'title_required' => false, 'max_images' => 9, 'max_videos' => 1, 'image_extensions' => ['jpg', 'jpeg', 'png'], 'video_extensions' => ['mp4']],
        'tiktok' => ['label' => 'TikTok', 'media_required' => true, 'text_required' => false, 'title_required' => false, 'max_images' => 35, 'max_videos' => 1, 'image_extensions' => ['jpg', 'jpeg', 'webp'], 'video_extensions' => ['mp4', 'mov', 'm4v', 'webm']],
        'youtube' => ['label' => 'YouTube', 'media_required' => true, 'text_required' => false, 'title_required' => true, 'max_images' => 0, 'max_videos' => 1, 'image_extensions' => [], 'video_extensions' => ['mp4', 'mov', 'm4v', 'webm']],
        'threads' => ['label' => 'Threads', 'media_required' => false, 'text_required' => false, 'title_required' => false, 'max_images' => 10, 'max_videos' => 1, 'image_extensions' => ['jpg', 'jpeg', 'png'], 'video_extensions' => ['mp4']],
        'pinterest' => ['label' => 'Pinterest', 'media_required' => true, 'text_required' => false, 'title_required' => false, 'max_images' => 1, 'max_videos' => 1, 'image_extensions' => ['jpg', 'jpeg', 'png'], 'video_extensions' => ['mp4']],
        'google' => ['label' => 'Google Business Profile', 'media_required' => false, 'text_required' => true, 'title_required' => false, 'max_images' => 1, 'max_videos' => 0, 'image_extensions' => ['jpg', 'jpeg', 'png'], 'video_extensions' => []],
    ],
];
