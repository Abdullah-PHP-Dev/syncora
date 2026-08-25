<?php

namespace Database\Seeders;

use App\Models\Integration;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IntegrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $integrations = [
            // Analytics & Tracking
            ['name' => 'Google Analytics',   'slug' => 'google_analytics',   'category' => 'analytics', 'icon_path' => 'bx-line-chart',  'description' => 'Track website traffic and audience behavior with GA4.'],
            ['name' => 'Microsoft Clarity',  'slug' => 'microsoft_clarity',  'category' => 'analytics', 'icon_path' => 'bxs-analyse',    'description' => 'See heatmaps and session recordings of how visitors use your site.'],

            // Pixels
            ['name' => 'Facebook Pixel',     'slug' => 'facebook_pixel',     'category' => 'pixels',    'icon_path' => 'bxl-facebook',   'description' => 'Track conversions and build ad audiences from your Facebook Pixel.'],
            ['name' => 'Snapchat Pixel',     'slug' => 'snapchat_pixel',     'category' => 'pixels',    'icon_path' => 'bxl-snapchat',   'description' => 'Measure and optimize your Snapchat ad campaigns.'],
            ['name' => 'TikTok Pixel',       'slug' => 'tiktok_pixel',       'category' => 'pixels',    'icon_path' => 'bxl-tiktok',     'description' => 'Track events and conversions for your TikTok ads.'],
            ['name' => 'X',                  'slug' => 'x_pixel',            'category' => 'pixels',    'icon_path' => 'bxl-twitter',    'description' => 'Track conversions from your X (Twitter) ad campaigns.'],

            // AI
            ['name' => 'Claude AI',          'slug' => 'claude_ai',          'category' => 'ai',        'icon_path' => 'bxs-brain',      'description' => "Power AI features in Socialeaz with Anthropic's Claude."],
            ['name' => 'ChatGPT AI',         'slug' => 'chatgpt_ai',         'category' => 'ai',        'icon_path' => 'bx-bot',         'description' => "Power AI features in Socialeaz with OpenAI's ChatGPT."],

            // Ad Platforms
            ['name' => 'Google Ads',         'slug' => 'google_ads',         'category' => 'ads',       'icon_path' => 'bxl-google',     'description' => 'Track conversions from your Google Ads campaigns.'],
            ['name' => 'Google Tag Manager', 'slug' => 'google_tag_manager', 'category' => 'ads',       'icon_path' => 'bxs-tag',        'description' => 'Manage all your tracking tags from one container.'],
        ];

        foreach ($integrations as $integration) {
            Integration::updateOrCreate(
                ['slug' => $integration['slug']],
                $integration + ['is_active' => true]
            );
        }
    }
}
