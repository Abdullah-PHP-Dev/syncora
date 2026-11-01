<?php

namespace Tests\Feature\Connections;

use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\AuthorizationPlanner;
use App\Services\Connections\CapabilityCatalog;
use App\Services\Connections\Drivers\LinkedInDriver;
use App\Services\Connections\Drivers\MetaDriver;
use App\Support\Connections\AuthorizationFlow;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** "Choose what to connect": catalog statuses, planner and guided flow. */
class CapabilityAuthorizationTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.facebook.client_id', 'fb');
        Settings::set('posts.linkedin.client_id', 'li');
        Settings::set('ads.linkedin.client_id', 'li-ads');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware();
    }

    private function connection(string $platform, string $step, array $capabilities, string $status = 'active', ?User $user = null): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => ($user ?? $this->user)->id, 'platform' => $platform, 'step' => $step,
            'provider_account_id' => uniqid(), 'status' => $status, 'capabilities' => $capabilities,
            'access_token' => 'token',
        ]);
    }

    private function statuses(string $platform): array
    {
        return collect(app(CapabilityCatalog::class)->forUser($this->user->id, $platform))->pluck('status', 'key')->all();
    }

    private function plan(string $platform, array $capabilities): array
    {
        return app(AuthorizationPlanner::class)->plan($this->user->id, $platform, $capabilities);
    }

    private function flow(): AuthorizationFlow
    {
        return app(AuthorizationFlow::class);
    }

    public function test_statuses_come_from_granted_capabilities_not_from_connecting(): void
    {
        $this->assertSame(array_fill_keys(['posting', 'ads', 'messaging', 'engagement', 'insights'], 'available'), $this->statuses('meta'));

        // Consent ran; the user unticked the ads permissions.
        $this->connection('meta', MetaDriver::LOGIN, ['posting', 'insights']);

        $this->assertSame(['posting' => 'connected', 'ads' => 'not_granted', 'messaging' => 'not_granted', 'engagement' => 'not_granted', 'insights' => 'connected'], $this->statuses('meta'));
    }

    public function test_expired_and_revoked_consents_need_a_reconnect(): void
    {
        $this->connection('meta', MetaDriver::LOGIN, ['posting'], SocialConnection::NEEDS_REAUTH);

        $this->assertSame('reconnect', $this->statuses('meta')['posting']);
        $this->assertSame('available', $this->statuses('meta')['ads']);
    }

    public function test_a_consent_the_user_disconnected_counts_as_never_connected(): void
    {
        $this->connection('meta', MetaDriver::LOGIN, ['posting'], SocialConnection::REVOKED)->forceFill(['last_error' => SocialConnection::DISCONNECTED_BY_USER])->save();

        $this->assertSame('available', $this->statuses('meta')['posting']);
    }

    public function test_approval_unsupported_and_unconfigured_capabilities_are_not_selectable(): void
    {
        $tiktok = collect(app(CapabilityCatalog::class)->forUser($this->user->id, 'tiktok'))->keyBy('key');

        // tiktok.business_messaging flag is off: TikTok hasn't approved it.
        $this->assertSame('approval_required', $tiktok['messaging']['status']);
        // No TikTok app configured in this environment.
        $this->assertSame('unavailable', $tiktok['posting']['status']);
        $this->assertFalse($tiktok['posting']['selectable']);
        $this->assertSame('unavailable', $this->statuses('linkedin')['messaging']);
        $this->assertSame([], app(CapabilityCatalog::class)->forUser($this->user->id, 'openai'));
    }

    public function test_capabilities_sharing_a_consent_get_one_sign_in(): void
    {
        $plan = $this->plan('meta', ['posting', 'ads', 'messaging']);

        $this->assertCount(1, $plan['steps']);
        $this->assertSame(MetaDriver::LOGIN, $plan['steps'][0]['key']);
        $this->assertSame(['posting', 'ads', 'messaging'], $plan['steps'][0]['capabilities']);
        $this->assertFalse($plan['steps'][0]['reconnect']);
    }

    public function test_capabilities_on_separate_consents_get_ordered_steps(): void
    {
        // Picked in reverse; the main consent still comes first.
        $plan = $this->plan('linkedin', ['ads', 'posting', 'messaging']);

        $this->assertSame([LinkedInDriver::PAGES, LinkedInDriver::ADS], array_column($plan['steps'], 'key'));
        $this->assertSame([['key' => 'messaging', 'status' => 'unavailable']], $plan['blocked']);
    }

    public function test_adding_a_capability_reruns_only_the_consent_that_grants_it(): void
    {
        $this->connection('linkedin', LinkedInDriver::PAGES, ['posting']);
        $this->connection('meta', MetaDriver::LOGIN, ['posting']);

        $linkedin = $this->plan('linkedin', ['posting', 'ads']);
        $this->assertSame([LinkedInDriver::ADS], array_column($linkedin['steps'], 'key'));
        $this->assertSame(['posting'], $linkedin['connected']);

        $meta = $this->plan('meta', ['posting', 'ads']);
        $this->assertSame(['ads'], $meta['steps'][0]['capabilities']);
        $this->assertTrue($meta['steps'][0]['reconnect']);
    }

    public function test_plan_endpoint_validates_against_the_catalog(): void
    {
        $this->postJson(route('admin.connections.plan', 'meta'), ['capabilities' => ['posting']])
            ->assertOk()->assertJsonPath('steps.0.key', MetaDriver::LOGIN);

        $this->postJson(route('admin.connections.plan', 'meta'), ['capabilities' => ['everything']])->assertStatus(422);
        $this->postJson(route('admin.connections.plan', 'meta'), ['capabilities' => []])->assertStatus(422);
        $this->postJson(route('admin.connections.plan', 'openai'), ['capabilities' => ['ads']])->assertNotFound();
    }

    public function test_authorize_goes_straight_to_the_first_provider_consent(): void
    {
        $response = $this->post(route('admin.connections.authorize', 'linkedin'), ['capabilities' => ['posting', 'ads']]);

        $response->assertRedirect();
        $this->assertStringContainsString('linkedin.com', $response->headers->get('Location'));
        $this->assertSame('hub', session('social_oauth_return_to'));

        $state = $this->flow()->state($this->user->id);
        $this->assertSame(['failed', 'pending'], array_column($state['steps'], 'state'));
        $this->assertSame(LinkedInDriver::PAGES, $state['next']['key']);
    }

    public function test_nothing_to_authorize_lands_on_the_summary(): void
    {
        $this->connection('meta', MetaDriver::LOGIN, ['posting']);

        $this->post(route('admin.connections.authorize', 'meta'), ['capabilities' => ['posting']])
            ->assertRedirect(route('admin.connections.index'));

        $state = $this->flow()->state($this->user->id);
        $this->assertTrue($state['complete']);
        $this->assertSame([['key' => 'posting', 'status' => 'connected']], $state['results']);
    }

    public function test_a_step_is_done_only_when_its_callback_recorded_the_consent(): void
    {
        Carbon::setTestNow('2026-11-01 10:00:00');
        $this->flow()->start($this->user->id, 'linkedin', ['posting', 'ads']);
        $this->flow()->continue($this->user->id);

        // Returned without a callback recording anything: cancelled / failed.
        $this->assertSame('failed', $this->flow()->state($this->user->id)['steps'][0]['state']);

        // An older consent for the same step doesn't count either.
        Carbon::setTestNow('2026-11-01 09:00:00');
        $old = $this->connection('linkedin', LinkedInDriver::PAGES, []);
        Carbon::setTestNow('2026-11-01 10:01:00');
        $this->assertSame('failed', $this->flow()->state($this->user->id)['steps'][0]['state']);

        // The callback records the consent (granted posting).
        $old->forceFill(['capabilities' => ['posting']])->save();
        $state = $this->flow()->state($this->user->id);
        $this->assertSame(['done', 'pending'], array_column($state['steps'], 'state'));
        $this->assertSame(LinkedInDriver::ADS, $state['next']['key']);
        $this->assertSame(1, $state['done']);
    }

    public function test_denied_permission_is_reported_and_offered_again(): void
    {
        Carbon::setTestNow('2026-11-01 10:00:00');
        $this->flow()->start($this->user->id, 'meta', ['posting', 'ads']);
        $this->flow()->continue($this->user->id);

        // Consent recorded, but the user unticked ads_management / ads_read.
        $this->connection('meta', MetaDriver::LOGIN, ['posting']);

        $state = $this->flow()->state($this->user->id);
        $this->assertTrue($state['complete']);
        $this->assertSame([['key' => 'posting', 'status' => 'connected'], ['key' => 'ads', 'status' => 'not_granted']], $state['results']);
        $this->assertSame(['ads'], $state['missing']);
    }

    public function test_skip_and_cancel(): void
    {
        $this->flow()->start($this->user->id, 'linkedin', ['posting', 'ads']);

        $this->post(route('admin.connections.flow.skip'))->assertRedirect(route('admin.connections.index'));
        $state = $this->flow()->state($this->user->id);
        $this->assertSame(['skipped', 'pending'], array_column($state['steps'], 'state'));
        $this->assertSame(LinkedInDriver::ADS, $state['next']['key']);

        $this->post(route('admin.connections.flow.cancel'))->assertRedirect(route('admin.connections.index'));
        $this->assertNull($this->flow()->state($this->user->id));
    }

    public function test_flow_expires(): void
    {
        $this->flow()->start($this->user->id, 'meta', ['posting']);

        Carbon::setTestNow(now()->addMinutes(31));

        $this->assertNull($this->flow()->state($this->user->id));
        $this->assertNull(session('connections.authorization'));
    }

    public function test_tenant_isolation(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $this->connection('meta', MetaDriver::LOGIN, ['posting', 'ads'], 'active', $other);

        // Another user's consent never counts for this one.
        $this->assertSame('available', $this->statuses('meta')['posting']);

        // A flow in this session belongs to the user who started it.
        $this->flow()->start($this->user->id, 'meta', ['posting']);
        $this->assertNull($this->flow()->state($other->id));
    }

    public function test_hub_page_carries_options_and_flow(): void
    {
        $this->flow()->start($this->user->id, 'meta', ['posting']);

        $data = app(\App\Http\Controllers\Admin\ConnectionHubController::class)->index()->getData();
        $meta = collect($data['hub']['cards'])->firstWhere('platform', 'meta');

        $this->assertSame('posting', $meta['capability_options'][0]['key']);
        $this->assertSame([], collect($data['hub']['cards'])->firstWhere('platform', 'openai')['capability_options']);
        $this->assertSame('meta', $data['flow']['platform']);
        // Nothing token-shaped reaches the browser.
        $this->assertStringNotContainsString('token', json_encode([$meta['capability_options'], $data['flow']]));
    }
}
