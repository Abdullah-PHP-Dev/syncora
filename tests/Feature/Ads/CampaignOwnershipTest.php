<?php

namespace Tests\Feature\Ads;

use App\Models\Admin\AdCampaign;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/**
 * Tenant isolation: one seller can never open, change, pause, duplicate or delete
 * another seller's campaign, on any platform, and nothing reaches the ad platform.
 */
class CampaignOwnershipTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private const PLATFORMS = ['facebook', 'instagram', 'google', 'youtube', 'tiktok', 'snapchat', 'x', 'linkedin', 'openai'];

    private User $me;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        $this->me = User::create(['name' => 'Me', 'email' => 'me@example.com', 'password' => bcrypt('x')]);
        $this->other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->me);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        Http::fake(); // any call that slipped through would be recorded
    }

    private function foreignCampaign(string $platform): AdCampaign
    {
        $account = SocialAccount::create([
            'user_id' => $this->other->id, 'platform' => in_array($platform, ['instagram'], true) ? 'facebook' : ($platform === 'youtube' ? 'google' : $platform),
            'platform_account_id' => 'acc-'.$platform, 'name' => 'Other '.$platform, 'access_token' => 'token', 'is_token_valid' => true, 'has_ads_permission' => true,
        ]);

        return AdCampaign::create(['user_id' => $this->other->id, 'social_account_id' => $account->id, 'platform' => $platform,
            'name' => 'Other campaign', 'status' => 'ACTIVE', 'ad_campaign_id' => 'remote-'.$platform]);
    }

    public function test_another_sellers_campaign_is_out_of_reach_on_every_platform(): void
    {
        foreach (self::PLATFORMS as $platform) {
            $c = $this->foreignCampaign($platform);
            $p = ['platform' => $platform, 'campaign' => $c->id];

            $this->get(route('admin.ads.campaigns.edit', $p))->assertNotFound();
            // Form validation runs first (422), otherwise the ownership check (404): never a change.
            $this->assertContains($this->putJson(route('admin.ads.campaigns.update', $p), ['name' => 'Hijacked'])->status(), [404, 422], "$platform update");
            $this->deleteJson(route('admin.ads.campaigns.destroy', $p))->assertNotFound();
            $this->patchJson(route('admin.ads.campaigns.status', ['platform' => $platform, 'id' => $c->id]), ['status' => 'PAUSED'])->assertNotFound();
            $this->postJson(route('admin.ads.campaigns.duplicate', ['platform' => $platform, 'id' => $c->id]))->assertStatus(404);

            $fresh = AdCampaign::find($c->id);
            $this->assertNotNull($fresh, "$platform campaign was deleted");
            $this->assertSame(['Other campaign', 'ACTIVE'], [$fresh->name, $fresh->status], "$platform campaign was changed");
        }
        Http::assertNothingSent();
    }

    public function test_an_id_from_another_platform_or_a_non_numeric_id_is_not_found(): void
    {
        $mine = AdCampaign::create(['user_id' => $this->me->id, 'platform' => 'google', 'name' => 'Mine', 'status' => 'ACTIVE', 'ad_campaign_id' => 'customers/1/campaigns/2']);

        $this->patchJson(route('admin.ads.campaigns.status', ['platform' => 'linkedin', 'id' => $mine->id]), ['status' => 'PAUSED'])->assertNotFound();
        $this->deleteJson(route('admin.ads.campaigns.destroy', ['platform' => 'x', 'campaign' => $mine->id]))->assertNotFound();
        $this->deleteJson(route('admin.ads.campaigns.destroy', ['platform' => 'google', 'campaign' => '1 OR 1=1']))->assertNotFound();
        $this->assertNotNull(AdCampaign::find($mine->id));
        Http::assertNothingSent();
    }
}
