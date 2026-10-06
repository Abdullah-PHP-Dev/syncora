<?php

namespace Tests\Feature\Connections;

use App\Models\SocialAccount;
use App\Models\SocialConnection;
use App\Models\User;
use App\Services\Connections\Drivers\MetaDriver;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Commit 7: connections:check-status. */
class ConnectionStatusJobTest extends TestCase
{
    use CreatesConnectionTables;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        Settings::set('posts.facebook.client_secret', 'app-secret');
        $this->user = User::create(['name' => 'Seller', 'email' => 's@example.com', 'password' => bcrypt('x')]);
    }

    private function fakeMetaOk(): void
    {
        Http::fake(['*' => Http::response(['data' => [['permission' => 'pages_manage_posts', 'status' => 'granted']]])]);
    }

    private function connection(array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'user_id' => $this->user->id, 'platform' => 'meta', 'step' => MetaDriver::LOGIN,
            'provider_account_id' => uniqid(), 'access_token' => 'user-token', 'status' => SocialConnection::ACTIVE,
            'expires_at' => now()->addDays(40), 'last_checked_at' => now(),
        ], $attributes));
    }

    public function test_expiry_pass_transitions(): void
    {
        $soon = $this->connection(['expires_at' => now()->addDays(3)]);
        $expired = $this->connection(['expires_at' => now()->subHour()]);
        $refreshable = $this->connection(['expires_at' => now()->subHour(), 'refresh_token' => 'r']);
        $fine = $this->connection();
        $neverExpires = $this->connection(['expires_at' => null]);

        $this->artisan('connections:check-status')->assertSuccessful();

        $this->assertSame(SocialConnection::EXPIRING, $soon->fresh()->status);
        $this->assertSame(SocialConnection::NEEDS_REAUTH, $expired->fresh()->status);
        // Refreshable: the access token's expiry doesn't matter (renewed on use).
        $this->assertSame(SocialConnection::ACTIVE, $refreshable->fresh()->status);
        $this->assertSame(SocialConnection::ACTIVE, $fine->fresh()->status);
        $this->assertSame(SocialConnection::ACTIVE, $neverExpires->fresh()->status);
    }

    public function test_refresh_token_expiry_governs_refreshable_connections(): void
    {
        $soon = $this->connection(['expires_at' => now()->subHour(), 'refresh_token' => 'r', 'refresh_expires_at' => now()->addDays(2)]);
        $gone = $this->connection(['expires_at' => now()->subHour(), 'refresh_token' => 'r', 'refresh_expires_at' => now()->subDay()]);

        $this->artisan('connections:check-status')->assertSuccessful();

        $this->assertSame(SocialConnection::EXPIRING, $soon->fresh()->status);
        $this->assertSame(SocialConnection::NEEDS_REAUTH, $gone->fresh()->status);
    }

    public function test_provider_reported_problems_are_not_cleared_by_time_and_revoked_is_untouched(): void
    {
        $reauth = $this->connection(['status' => SocialConnection::NEEDS_REAUTH]);
        $error = $this->connection(['status' => SocialConnection::ERROR]);
        $revoked = $this->connection(['status' => SocialConnection::REVOKED, 'expires_at' => now()->subDay()]);

        $this->artisan('connections:check-status')->assertSuccessful();

        $this->assertSame(SocialConnection::NEEDS_REAUTH, $reauth->fresh()->status);
        $this->assertSame(SocialConnection::ERROR, $error->fresh()->status);
        $this->assertSame(SocialConnection::REVOKED, $revoked->fresh()->status);
    }

    public function test_dry_run_reports_without_writing_or_calling_providers(): void
    {
        Http::fake();
        $expired = $this->connection(['expires_at' => now()->subHour(), 'last_checked_at' => null]);

        $this->artisan('connections:check-status', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('needs_reauth')
            ->assertSuccessful();

        $this->assertSame(SocialConnection::ACTIVE, $expired->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_broken_connection_marks_its_assets_invalid(): void
    {
        $expired = $this->connection(['expires_at' => now()->subHour()]);
        $asset = SocialAccount::create(['user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => 'p', 'name' => 'P', 'is_token_valid' => true, 'social_connection_id' => $expired->id]);

        $this->artisan('connections:check-status')->assertSuccessful();

        $this->assertFalse($asset->fresh()->is_token_valid);
    }

    public function test_validation_pass_checks_only_stale_connections_up_to_the_limit(): void
    {
        $this->fakeMetaOk();
        $fresh = $this->connection(['last_checked_at' => now()->subHour()]);
        $stale = $this->connection(['last_checked_at' => now()->subDays(2)]);
        $never = $this->connection(['last_checked_at' => null]);

        $this->artisan('connections:check-status', ['--limit' => 1])->assertSuccessful();

        // Oldest first: never-checked wins the single slot.
        $this->assertNotNull($never->fresh()->last_checked_at);
        $this->assertTrue($stale->fresh()->last_checked_at->lt(now()->subDay()));
        $this->assertTrue($fresh->fresh()->last_checked_at->gt(now()->subDay()));
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'me/permissions'));
    }

    public function test_provider_revocation_is_learned_and_reported(): void
    {
        $connection = $this->connection(['last_checked_at' => null]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'App not installed', 'code' => 190, 'error_subcode' => 458]], 400)]);

        $this->artisan('connections:check-status')
            ->expectsOutputToContain('revoked')
            ->assertSuccessful();

        $this->assertSame(SocialConnection::REVOKED, $connection->fresh()->status);
    }

    public function test_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('0   * * * *  php artisan connections:check-status')
            ->assertSuccessful();
    }

    public function test_needs_attention_excludes_user_disconnects(): void
    {
        $this->connection(['status' => SocialConnection::NEEDS_REAUTH]);
        $this->connection(['status' => SocialConnection::REVOKED, 'last_error' => SocialConnection::DISCONNECTED_BY_USER]);
        $this->connection(['status' => SocialConnection::REVOKED, 'last_error' => 'App not installed']);
        $this->connection();

        $this->assertSame(2, SocialConnection::attentionNeeded()->count());
    }
}
