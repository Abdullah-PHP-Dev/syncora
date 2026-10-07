<?php

namespace Tests\Feature\Connections;

use App\Support\Connections\HubLink;
use Tests\TestCase;

/** Commit 8b: module connect buttons point Meta at the Connection Hub. */
class HubLinkTest extends TestCase
{
    public function test_meta_platforms_are_managed_by_the_hub(): void
    {
        foreach (['facebook', 'instagram', 'whatsapp'] as $platform) {
            $this->assertTrue(HubLink::managed($platform));
            $this->assertStringEndsWith('/connections#meta', HubLink::for($platform));
        }
        foreach (['google', 'youtube'] as $platform) {
            $this->assertStringEndsWith('/connections#google', HubLink::for($platform));
        }
    }

    public function test_every_social_platform_is_managed_and_others_are_not(): void
    {
        foreach (['linkedin', 'tiktok', 'snapchat', 'threads', 'pinterest'] as $platform) {
            $this->assertStringEndsWith('/connections#' . $platform, HubLink::for($platform));
        }
        // Messaging channels and unknown platforms keep their own setup.
        foreach (['telegram', 'discord', 'reddit'] as $platform) {
            $this->assertFalse(HubLink::managed($platform));
            $this->assertNull(HubLink::for($platform));
        }
    }

    public function test_ads_account_switcher_sends_meta_to_the_hub(): void
    {
        $facebook = view('admin.ads.partials.account-switcher', ['platform' => 'facebook', 'account' => null, 'adAccounts' => collect()])->render();
        // A platform without a Hub card keeps its module's own connect flow.
        $other = view('admin.ads.partials.account-switcher', ['platform' => 'reddit', 'account' => null, 'adAccounts' => collect()])->render();

        $this->assertStringContainsString('/connections#meta', $facebook);
        $this->assertStringNotContainsString('/connections#', $other);
        $this->assertStringContainsString('/ads/reddit/redirect', $other);
    }
}
