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
    }

    public function test_other_platforms_keep_their_own_flow_for_now(): void
    {
        foreach (['tiktok', 'x', 'google', 'youtube', 'linkedin', 'snapchat'] as $platform) {
            $this->assertFalse(HubLink::managed($platform));
            $this->assertNull(HubLink::for($platform));
        }
    }

    public function test_ads_account_switcher_sends_meta_to_the_hub(): void
    {
        $facebook = view('admin.ads.partials.account-switcher', ['platform' => 'facebook', 'account' => null, 'adAccounts' => collect()])->render();
        $tiktok = view('admin.ads.partials.account-switcher', ['platform' => 'tiktok', 'account' => null, 'adAccounts' => collect()])->render();

        $this->assertStringContainsString('/connections#meta', $facebook);
        $this->assertStringNotContainsString('/connections#meta', $tiktok);
        $this->assertStringContainsString('/ads/tiktok/redirect', $tiktok);
    }
}
