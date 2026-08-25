<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'category',
        'icon_path',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function userIntegrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class);
    }

    /**
     * What the Setup tab's form should collect for this service, keyed by
     * slug rather than a DB column - every one of these is a single pasted
     * ID/token (no OAuth service exists here), so the shape rarely changes
     * and doesn't warrant its own schema/migration.
     */
    public function getCredentialFieldsAttribute(): array
    {
        return match ($this->slug) {
            'google_analytics'  => [['key' => 'measurement_id', 'label' => 'Measurement ID', 'placeholder' => 'G-XXXXXXXXXX', 'type' => 'text']],
            'facebook_pixel'    => [['key' => 'pixel_id', 'label' => 'Pixel ID', 'placeholder' => '000000000000000', 'type' => 'text']],
            'snapchat_pixel'    => [['key' => 'pixel_id', 'label' => 'Pixel ID', 'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'type' => 'text']],
            'tiktok_pixel'      => [['key' => 'pixel_id', 'label' => 'Pixel ID', 'placeholder' => 'XXXXXXXXXXXXXXXXXXXX', 'type' => 'text']],
            'x_pixel'           => [['key' => 'pixel_id', 'label' => 'Pixel ID', 'placeholder' => 'oXXXX', 'type' => 'text']],
            'microsoft_clarity' => [['key' => 'project_id', 'label' => 'Project ID', 'placeholder' => 'xxxxxxxxxx', 'type' => 'text']],
            'claude_ai'         => [['key' => 'api_key', 'label' => 'API Key', 'placeholder' => 'sk-ant-...', 'type' => 'password']],
            'chatgpt_ai'        => [['key' => 'api_key', 'label' => 'API Key', 'placeholder' => 'sk-...', 'type' => 'password']],
            'google_ads'        => [['key' => 'conversion_id', 'label' => 'Conversion ID', 'placeholder' => 'AW-XXXXXXXXX', 'type' => 'text']],
            'google_tag_manager'=> [['key' => 'container_id', 'label' => 'Container ID', 'placeholder' => 'GTM-XXXXXXX', 'type' => 'text']],
            default             => [['key' => 'api_key', 'label' => 'API Key / ID', 'placeholder' => '', 'type' => 'text']],
        };
    }

    /**
     * "How To" tab content - a short explainer plus the official docs link
     * for where to find the value the Setup tab asks for.
     */
    public function getHowToAttribute(): array
    {
        return match ($this->slug) {
            'google_analytics'  => ['text' => 'Find your Measurement ID in Google Analytics under Admin > Data Streams > (your stream) > Measurement ID.', 'url' => 'https://support.google.com/analytics/answer/9539598'],
            'facebook_pixel'    => ['text' => 'Find your Pixel ID in Meta Events Manager > Data Sources > (your pixel).', 'url' => 'https://www.facebook.com/business/help/952192354843755'],
            'snapchat_pixel'    => ['text' => 'Find your Pixel ID in Snapchat Ads Manager > Events Manager.', 'url' => 'https://businesshelp.snapchat.com/s/article/pixel-website-install'],
            'tiktok_pixel'      => ['text' => 'Find your Pixel ID in TikTok Ads Manager > Assets > Events > Web Events.', 'url' => 'https://ads.tiktok.com/help/article/get-started-pixel'],
            'x_pixel'           => ['text' => 'Find your Pixel/Website Tag ID in X (Twitter) Ads > Tools > Conversion Tracking.', 'url' => 'https://business.x.com/en/help/campaign-measurement-and-analytics/conversion-tracking-for-websites.html'],
            'microsoft_clarity' => ['text' => 'Find your Project ID in Microsoft Clarity > Setup > Install Tracking Code.', 'url' => 'https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-setup'],
            'claude_ai'         => ['text' => 'Create an API key in the Anthropic Console under API Keys.', 'url' => 'https://console.anthropic.com/settings/keys'],
            'chatgpt_ai'        => ['text' => 'Create an API key in the OpenAI Platform under API Keys.', 'url' => 'https://platform.openai.com/api-keys'],
            'google_ads'        => ['text' => 'Find your Conversion ID in Google Ads > Tools > Conversions (it starts with AW-).', 'url' => 'https://support.google.com/google-ads/answer/6331304'],
            'google_tag_manager'=> ['text' => 'Find your Container ID in Google Tag Manager > Admin > Container Settings.', 'url' => 'https://support.google.com/tagmanager/answer/6103696'],
            default             => ['text' => 'Paste the credential provided by this service.', 'url' => null],
        };
    }
}
