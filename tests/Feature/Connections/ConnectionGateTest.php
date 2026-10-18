<?php

namespace Tests\Feature\Connections;

use App\Events\UserRegistered;
use App\Http\Middleware\EnsureConnectedAccount;
use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Connections\Drivers\MetaDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** Posts / Inbox / Comments need one eligible connected account (EnsureConnectedAccount). */
class ConnectionGateTest extends TestCase
{
    use CreatesConnectionTables;

    private const GATED = ['admin.posts.dashboard', 'admin.posts.composer', 'admin.posts.index', 'admin.chats.dashboard', 'admin.comments.dashboard'];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
        ]);
        $this->createConnectionTables();

        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
    }

    private function connection(string $status = SocialConnection::ACTIVE, ?User $user = null): SocialConnection
    {
        return SocialConnection::create([
            'user_id' => ($user ?? $this->user)->id, 'platform' => 'meta', 'step' => MetaDriver::LOGIN, 'provider_account_id' => 'fb-' . uniqid(),
            'access_token' => 'user-token', 'status' => $status, 'expires_at' => now()->addDays(40),
            'capabilities' => ['posting', 'messaging', 'ads'],
        ]);
    }

    private function account(array $overrides = []): SocialAccount
    {
        return SocialAccount::create($overrides + [
            'user_id' => $this->user->id, 'platform' => 'facebook', 'account_type' => 'page', 'platform_account_id' => (string) random_int(1, 1e9),
            'name' => 'Page', 'access_token' => 'p', 'is_token_valid' => true, 'has_posting_permission' => true,
        ]);
    }

    /** Runs the middleware alone, as the gated routes would. */
    private function gate(?Request $request = null): Response
    {
        $request ??= Request::create('/posts/composer');
        $request->setUserResolver(fn () => $this->user->fresh());

        return app(EnsureConnectedAccount::class)->handle($request, fn () => response('page'));
    }

    private function assertAllowed(): void
    {
        $this->assertSame('page', $this->gate()->getContent());
    }

    private function assertBlocked(): void
    {
        $response = $this->gate();
        $this->assertTrue($response->isRedirect(route('admin.connections.index')));
    }

    public function test_exactly_the_five_pages_are_gated_and_the_hub_is_not(): void
    {
        foreach (self::GATED as $name) {
            $this->assertContains('connected', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }

        foreach (['admin.connections.index', 'admin.connections.connect', 'dashboard', 'admin.posts.store', 'admin.chats.channels'] as $name) {
            $this->assertNotContains('connected', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }

    public function test_zero_connections_redirects_every_gated_url_to_the_hub(): void
    {
        foreach (self::GATED as $name) {
            $this->get(route($name))
                ->assertRedirect(route('admin.connections.index'))
                ->assertSessionHas('error', __('connections.gate.required'));
        }

        // The bare /posts alias of the dashboard too.
        $this->get(url('posts'))->assertRedirect(route('admin.connections.index'));
    }

    public function test_json_requests_get_a_403_instead_of_a_redirect(): void
    {
        $this->getJson(route('admin.posts.composer'))
            ->assertForbidden()
            ->assertJson(['code' => 'connection_required', 'connect_url' => route('admin.connections.index')]);
    }

    public function test_subscription_check_still_runs_first(): void
    {
        Subscription::where('user_id', $this->user->id)->delete();

        $this->get(route('admin.posts.composer'))->assertRedirect(route('admin.subscription.select'));
    }

    public function test_one_valid_connected_account_allows_access(): void
    {
        $this->account(['social_connection_id' => $this->connection()->id]);

        $this->assertAllowed();
        $this->assertTrue($this->user->hasEligibleConnection());
    }

    public function test_expiring_connection_still_allows_access(): void
    {
        $this->account(['social_connection_id' => $this->connection(SocialConnection::EXPIRING)->id]);

        $this->assertAllowed();
    }

    public function test_messaging_only_account_counts_but_ad_only_does_not(): void
    {
        $ad = $this->account(['account_type' => 'ad_account', 'has_posting_permission' => false, 'has_ads_permission' => true]);
        $this->assertBlocked();

        $ad->delete();
        $this->account(['platform' => 'telegram', 'account_type' => 'bot', 'has_posting_permission' => false, 'has_messaging_permission' => true]);
        $this->assertAllowed();
    }

    public function test_disconnected_or_broken_connections_do_not_count(): void
    {
        foreach ([SocialConnection::EXPIRED, SocialConnection::NEEDS_REAUTH, SocialConnection::REVOKED, SocialConnection::ERROR] as $status) {
            // Asset still flagged valid - the consent's status alone must block.
            $account = $this->account(['social_connection_id' => $this->connection($status)->id]);
            $this->assertBlocked();
            $account->delete();
        }
    }

    public function test_hub_disconnect_blocks_access_again(): void
    {
        $connection = $this->connection();
        $this->account(['social_connection_id' => $connection->id]);
        $this->assertAllowed();

        \Illuminate\Support\Facades\Http::fake();
        app(\App\Services\Connections\ConnectionService::class)->disconnect($connection);

        $this->assertBlocked();
    }

    public function test_invalid_token_or_switched_off_asset_does_not_count(): void
    {
        $account = $this->account(['is_token_valid' => false]);
        $this->assertBlocked();

        $account->update(['is_token_valid' => true, 'access_token' => null]);
        $this->assertBlocked();

        // Asset picker: posting and messaging both switched off in the Hub.
        $account->update(['access_token' => 'p', 'enabled_capabilities' => ['insights']]);
        $this->assertBlocked();

        $account->update(['enabled_capabilities' => ['posting']]);
        $this->assertAllowed();
    }

    public function test_legacy_asset_without_a_hub_connection_counts(): void
    {
        $this->account(['social_connection_id' => null]);

        $this->assertAllowed();
    }

    public function test_another_users_account_does_not_count(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $this->account(['user_id' => $other->id, 'social_connection_id' => $this->connection(user: $other)->id]);

        $this->assertBlocked();
    }

    public function test_workspace_member_is_sent_to_their_workspaces_not_the_hub(): void
    {
        $request = Request::create('/chats/dashboard');
        $request->attributes->set('workspace_member', new \stdClass);

        $response = $this->gate($request);

        $this->assertTrue($response->isRedirect(route('seller-workspaces.index')));
    }

    public function test_hub_page_shows_the_onboarding_banner_until_connected(): void
    {
        $controller = app(\App\Http\Controllers\Admin\ConnectionHubController::class);
        $this->assertFalse($controller->index()->getData()['eligible']);

        $this->account();
        $this->assertTrue($controller->index()->getData()['eligible']);
    }

    public function test_registration_lands_on_the_connections_page(): void
    {
        Event::fake([UserRegistered::class]);
        auth()->logout();

        $this->post(route('register'), [
            'name' => 'New Seller', 'email' => 'new@example.com', 'password' => 'Password-123!', 'password_confirmation' => 'Password-123!',
        ])->assertRedirect(route('admin.connections.index'));

        $this->assertAuthenticated();
    }
}
