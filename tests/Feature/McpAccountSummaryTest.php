<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Services\PackageAccessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class McpAccountSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('social_connections', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->string('platform'); $table->string('status');
        });
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->string('platform');
        });
    }

    private function signIn(array $abilities = ['mcp:read'], bool $active = true): User
    {
        $user = new User;
        $user->forceFill(['id' => 123, 'name' => 'Seller', 'email' => 'seller@example.com', 'is_active' => $active]);
        Sanctum::actingAs($user, $abilities);
        return $user;
    }

    public function test_counts_are_scoped_to_the_user_with_platform_breakdown(): void
    {
        $this->signIn();
        DB::table('social_connections')->insert([
            ['user_id' => 123, 'platform' => 'facebook', 'status' => 'active'],
            ['user_id' => 123, 'platform' => 'facebook', 'status' => 'revoked'],
            ['user_id' => 123, 'platform' => 'instagram', 'status' => 'expiring'],
            ['user_id' => 999, 'platform' => 'youtube', 'status' => 'active'],
        ]);
        DB::table('social_accounts')->insert([
            ['user_id' => 123, 'platform' => 'facebook'], ['user_id' => 123, 'platform' => 'facebook'],
            ['user_id' => 123, 'platform' => 'pinterest'], ['user_id' => 999, 'platform' => 'youtube'],
        ]);
        $this->getJson('/api/mcp/v1/connection-summary?user_id=999')->assertOk()->assertExactJson([
            'total_connections' => 3, 'usable_connections' => 2, 'total_accounts' => 3,
            'platforms' => [
                ['platform' => 'facebook', 'total_connections' => 2, 'usable_connections' => 1, 'total_accounts' => 2],
                ['platform' => 'instagram', 'total_connections' => 1, 'usable_connections' => 1, 'total_accounts' => 0],
                ['platform' => 'pinterest', 'total_connections' => 0, 'usable_connections' => 0, 'total_accounts' => 1],
            ],
        ]);
    }

    public function test_empty_account_reports_zero(): void
    {
        $this->signIn();
        $this->getJson('/api/mcp/v1/connection-summary')->assertOk()->assertExactJson([
            'total_connections' => 0, 'usable_connections' => 0, 'total_accounts' => 0, 'platforms' => [],
        ]);
    }

    public function test_summary_requires_authentication_read_ability_and_active_user(): void
    {
        $this->getJson('/api/mcp/v1/connection-summary')->assertUnauthorized();
        $this->signIn(['mcp:media:write']);
        $this->getJson('/api/mcp/v1/connection-summary')->assertForbidden();
        $this->signIn(['mcp:read'], false);
        $this->getJson('/api/mcp/v1/connection-summary')->assertForbidden();
    }

    public function test_profile_exposes_subscription_plan_and_dates(): void
    {
        $user = $this->signIn();
        $subscription = new Subscription;
        $subscription->forceFill(['bundle_name' => 'Pro', 'status' => 'active', 'start_date' => '2026-11-01', 'end_date' => '2026-12-01']);
        $access = Mockery::mock(PackageAccessService::class);
        $access->shouldReceive('subscription')->once()->with($user)->andReturn($subscription);
        $access->shouldReceive('active')->once()->with($subscription)->andReturn(true);
        $this->app->instance(PackageAccessService::class, $access);
        $this->getJson('/api/mcp/v1/me')->assertOk()->assertJsonPath('subscription.bundle_name', 'Pro')->assertJsonPath('subscription.end_date', '2026-12-01')->assertJsonPath('subscription.is_active', true);
    }
}
