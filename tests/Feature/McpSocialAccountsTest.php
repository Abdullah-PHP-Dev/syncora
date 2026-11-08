<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpSocialAccountsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('social_accounts', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->string('platform'); $t->string('name')->nullable(); $t->string('username')->nullable();
            $t->boolean('is_token_valid')->default(true); $t->boolean('has_posting_permission')->default(false);
            $t->string('access_token')->nullable(); $t->string('metadata')->nullable();
        });
    }

    private function signIn(array $abilities = ['mcp:read'], bool $active = true): void
    {
        $user = new User;
        $user->forceFill(['id' => 123, 'is_active' => $active]);
        Sanctum::actingAs($user, $abilities);
    }

    public function test_owned_accounts_include_non_posting_assets_without_exposing_secrets(): void
    {
        $this->signIn();
        DB::table('social_accounts')->insert([
            ['user_id' => 123, 'platform' => 'instagram', 'name' => 'My profile', 'access_token' => 'secret'],
            ['user_id' => 123, 'platform' => 'facebook', 'name' => 'My ad account', 'access_token' => 'secret'],
            ['user_id' => 999, 'platform' => 'instagram', 'name' => 'Other profile', 'access_token' => 'secret'],
        ]);
        $this->getJson('/api/mcp/v1/social-accounts?user_id=999')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.has_posting_permission', false)->assertDontSee('secret')->assertDontSee('Other profile')->assertDontSee('user_id');
    }

    public function test_filtering_and_pagination_are_bounded_and_stable(): void
    {
        $this->signIn();
        foreach (['instagram', 'facebook', 'instagram'] as $platform) { DB::table('social_accounts')->insert(['user_id' => 123, 'platform' => $platform]); }
        $this->getJson('/api/mcp/v1/social-accounts?platform=instagram&per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.0.id', 3)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/mcp/v1/social-accounts?per_page=51')->assertUnprocessable();
        $this->getJson('/api/mcp/v1/social-accounts?page=0')->assertUnprocessable();
    }

    public function test_authentication_read_ability_and_active_user_are_required(): void
    {
        $this->getJson('/api/mcp/v1/social-accounts')->assertUnauthorized();
        $this->signIn(['mcp:posts:write']);
        $this->getJson('/api/mcp/v1/social-accounts')->assertForbidden();
        $this->signIn(['mcp:read'], false);
        $this->getJson('/api/mcp/v1/social-accounts')->assertForbidden();
    }
}
