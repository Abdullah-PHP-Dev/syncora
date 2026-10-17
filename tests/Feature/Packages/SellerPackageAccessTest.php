<?php

namespace Tests\Feature\Packages;

use App\Models\Bundle;
use App\Models\EmailMarketing\EmailCampaign;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EmailMarketingServices\SendGridCampaignService;
use App\Services\PackageAccessService;
use App\Services\PackageUsageService;
use App\Services\PostServices\XPostService;
use App\Services\SellerTeamService;
use App\Services\SubscriptionService;
use App\Support\Settings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Feature\Support\Concerns\CreatesSupportModuleTables;
use Tests\TestCase;

class SellerPackageAccessTest extends TestCase
{
    use CreatesSupportModuleTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->createSupportModuleTables();
        foreach (['2026_08_17_233010_create_bundles_table', '2026_08_17_234708_create_subscriptions_table', '2026_08_18_000800_create_subscription_cycles_table', '2026_10_17_120000_create_package_entitlements'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('size')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('platform')->default('facebook');
            $table->string('name')->nullable();
            $table->boolean('has_posting_permission')->default(false);
            $table->boolean('has_ads_permission')->default(false);
            $table->boolean('has_messaging_permission')->default(false);
            $table->timestamps();
        });
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('pending');
            $table->string('visibility')->default('public');
            $table->string('post_id')->nullable();
            $table->timestamp('schedule_at')->nullable();
            $table->timestamps();
        });
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_18_120000_add_seller_package_access.php'))->up();
        (require database_path('migrations/2026_10_18_140000_create_seller_team_members.php'))->up();
        foreach (['admin', 'customer_support', 'seller'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        Route::middleware(['web', 'auth', 'seller', 'subscription', 'package.feature'])->group(function () {
            Route::get('/_package/inbox', fn () => response()->json(['ok' => true]))->name('admin.chats.package-test');
            Route::post('/_package/inbox', fn () => response()->json(['ok' => true]))->name('admin.chats.package-write');
            Route::get('/_package/inbox/connect', fn () => response()->json(['ok' => true]))->name('admin.messaging.auth.test.redirect');
            Route::post('/_package/ai', fn () => response()->json(['ok' => true]))->name('admin.posts.generate-ai-test');
            Route::post('/_package/ai-fail', fn () => response()->json(['message' => 'invalid'], 422))->name('admin.posts.generate-ai-fail');
        });
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(12, 0));
    }

    private function seller(): User
    {
        $user = User::factory()->create();
        $user->assignRole('seller');

        return $user;
    }

    private function subscribe(User $user, array $access = [], array $limits = [], array $attributes = []): Subscription
    {
        $bundle = Bundle::create([
            'name_en' => 'Test package', 'name_ar' => 'باقة الاختبار', 'slug' => 'test-'.$user->id,
            'price' => 49, 'is_active' => true,
            'access_settings' => array_replace(array_fill_keys(array_keys(config('package_access.modules')), ['mode' => 'included', 'tier' => 'standard']), $access),
            'entitlement_settings' => array_replace(['publishing_accounts' => 2, 'advertising_accounts' => 1, 'scheduled_published_posts' => 2, 'ai_credits' => 2, 'hashtag_generation' => 2, 'content_categories' => 2, 'media_storage_gb' => 1], $limits),
            'meta' => ['allowance_period' => 'monthly'],
        ]);

        return Subscription::create(array_replace(['user_id' => $user->id, 'bundle_id' => $bundle->id, 'bundle_name' => 'Test package', 'status' => 'active', 'is_active' => true, 'start_date' => '2026-10-01', 'end_date' => '2027-10-01', 'billing_period' => 12], $attributes));
    }

    private function assertLimit(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected package limit denial.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('package', $exception->errors());
        }
    }

    public function test_team_invitations_reserve_seats_and_reject_duplicates_and_foreign_removal(): void
    {
        $owner = $this->seller();
        $this->subscribe($owner, limits: ['team_members' => 2]);
        $this->actingAs($owner)->get('/seller-team')->assertOk()->assertSee('My team');
        $response = $this->postJson('/seller-team', ['email' => 'member@example.test', 'permissions' => ['publishing' => 'read']])
            ->assertCreated()->assertJsonStructure(['invitation_url']);
        $id = $response->json('member.id');
        $this->postJson('/seller-team', ['email' => 'MEMBER@example.test', 'permissions' => ['publishing' => 'read']])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/seller-team', ['email' => 'second@example.test', 'permissions' => ['publishing' => 'write']])->assertUnprocessable()->assertJsonValidationErrors('package');
        $other = $this->seller();
        $this->actingAs($other)->deleteJson('/seller-team/'.$id)->assertNotFound();
        $this->actingAs($owner)->deleteJson('/seller-team/'.$id)->assertNoContent();
        $this->postJson('/seller-team', ['email' => 'second@example.test', 'permissions' => ['publishing' => 'write']])->assertCreated();
    }

    public function test_team_owner_only_package_and_disabled_permission_cannot_invite(): void
    {
        $owner = $this->seller();
        $subscription = $this->subscribe($owner, limits: ['team_members' => 1]);
        $this->actingAs($owner)->postJson('/seller-team', ['email' => 'member@example.test', 'permissions' => ['publishing' => 'write']])
            ->assertUnprocessable()->assertJsonValidationErrors('package');
        $subscription->bundle->update(['access_settings' => ['team_management' => ['mode' => 'disabled']]]);
        $this->postJson('/seller-team', ['email' => 'member@example.test', 'permissions' => ['publishing' => 'write']])->assertForbidden();
        $subscription->update(['end_date' => '2026-10-17']);
        $this->get('/seller-team')->assertOk();
        $this->postJson('/seller-team', ['email' => 'member@example.test', 'permissions' => ['publishing' => 'write']])->assertForbidden();
    }

    public function test_invitation_requires_matching_identity_and_cannot_be_replayed_or_accepted_after_downgrade(): void
    {
        $owner = $this->seller();
        $subscription = $this->subscribe($owner, limits: ['team_members' => 3]);
        $teams = app(SellerTeamService::class);
        [$member, $token] = $teams->invite($owner, 'member@example.test', ['publishing' => 'read']);
        $wrong = $this->seller();
        $this->actingAs($wrong)->postJson('/workspaces/invitations/'.$token)->assertForbidden();
        $user = User::factory()->create(['email' => 'member@example.test']);
        $this->actingAs($user)->get('/workspaces/invitations/'.$token)->assertOk();
        $this->post('/workspaces/invitations/'.$token)->assertRedirect(route('seller-workspaces.index'));
        $this->assertSame($user->id, $member->fresh()->user_id);
        $this->assertNull($member->fresh()->token_hash);
        $this->postJson('/workspaces/invitations/'.$token)->assertNotFound();
        [$pending, $token2] = $teams->invite($owner, 'second@example.test', ['publishing' => 'read']);
        $subscription->bundle->update(['entitlement_settings' => array_replace($subscription->bundle->entitlement_settings, ['team_members' => 1])]);
        $second = User::factory()->create(['email' => 'second@example.test']);
        $this->actingAs($second)->postJson('/workspaces/invitations/'.$token2)->assertUnprocessable()->assertJsonValidationErrors('package');
        $this->assertFalse($teams->usable($member->fresh()));
    }

    public function test_shared_workspace_enforces_member_permissions_owner_package_and_resource_ownership(): void
    {
        $owner = $this->seller();
        $subscription = $this->subscribe($owner, limits: ['team_members' => 3]);
        $teams = app(SellerTeamService::class);
        [$member, $token] = $teams->invite($owner, 'member@example.test', ['publishing' => 'read', 'ai_content' => 'write', 'inbox' => 'read']);
        $user = User::factory()->create(['email' => 'member@example.test']);
        $teams->accept($user, $token);
        Route::middleware(['web', 'auth', 'seller', 'subscription', 'package.feature'])->get('/_package/workspace', fn () => response()->json(['owner' => auth()->id(), 'actor' => request()->attributes->get('workspace_actor')?->id]))->name('admin.posts.workspace-test');
        Route::middleware(['web', 'auth', 'seller', 'subscription', 'package.feature'])->get('/_package/workspace-view', fn () => view('admin.seller-team.workspaces', ['memberships' => collect()]))->name('admin.posts.workspace-view');
        Route::middleware(['web', 'auth'])->post('/broadcasting/auth', fn () => response()->json(['owner' => auth()->id()]));
        $this->actingAs($user)->post('/workspaces/'.$member->id.'/enter')->assertRedirect();
        $this->get('/_package/workspace-view')->assertOk()->assertSee('Working in')->assertDontSee('My team');
        $this->getJson('/_package/workspace')->assertOk()->assertJsonPath('owner', $owner->id)->assertJsonPath('actor', $user->id);
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-inbox.'.$owner->id])->assertOk()->assertJsonPath('owner', $owner->id);
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.'.$owner->id])->assertForbidden();
        $this->assertSame($user->id, auth()->id());
        $this->postJson('/posts/generate-ai-content', ['prompt' => 'example'])->assertForbidden();
        $this->getJson('/subscription/checkout')->assertForbidden();
        $this->getJson('/plans')->assertForbidden();
        $this->deleteJson('/seller-team/'.$member->id)->assertForbidden();
        $this->getJson('/posts/facebook/redirect')->assertForbidden();
        $member->update(['permissions' => ['publishing' => 'write', 'ai_content' => 'write']]);
        $subscription->bundle->update(['entitlement_settings' => array_replace($subscription->bundle->entitlement_settings, ['publishing_accounts' => 0])]);
        $this->getJson('/_package/workspace')->assertForbidden()->assertJsonPath('feature', 'publishing');
        $this->post('/workspaces/leave')->assertRedirect();
        $this->assertSame($user->id, auth()->id());
    }

    public function test_expired_invitations_and_removed_members_cannot_enter_workspace(): void
    {
        $owner = $this->seller();
        $this->subscribe($owner, limits: ['team_members' => 2]);
        $teams = app(SellerTeamService::class);
        [$member, $token] = $teams->invite($owner, 'member@example.test', ['publishing' => 'write']);
        $member->update(['expires_at' => now()->subMinute()]);
        $user = User::factory()->create(['email' => 'member@example.test']);
        $this->actingAs($user)->postJson('/workspaces/invitations/'.$token)->assertNotFound();
        [$member, $token] = $teams->invite($owner, 'member@example.test', ['publishing' => 'write']);
        $teams->accept($user, $token);
        $this->post('/workspaces/'.$member->id.'/enter')->assertRedirect();
        $member->delete();
        $this->getJson('/posts/composer')->assertForbidden();
        $this->assertFalse(session()->has('seller_workspace_member'));
        $this->get('/workspaces')->assertOk();
    }

    public function test_zero_publishing_capacity_blocks_composer_and_ai_before_provider_calls(): void
    {
        Http::fake();
        $seller = $this->seller();
        $subscription = $this->subscribe($seller, limits: ['publishing_accounts' => 0]);
        $this->actingAs($seller)->getJson('/posts/composer')
            ->assertForbidden()->assertJsonPath('feature', 'publishing');
        $this->postJson('/posts/generate-ai-content', ['prompt' => 'Example', 'kind' => 'description'])
            ->assertForbidden()->assertJsonPath('code', 'package_feature_denied')->assertJsonPath('feature', 'publishing');
        $this->assertSame(0, app(PackageUsageService::class)->used($subscription, 'ai_credits'));
        Http::assertNothingSent();
        $subscription->bundle->update(['entitlement_settings' => array_replace($subscription->bundle->entitlement_settings, ['publishing_accounts' => -1])]);
        $this->assertTrue(app(PackageAccessService::class)->allows($seller, 'publishing', true));
    }

    public function test_preview_allows_reading_but_denies_mutation_and_oauth_get_side_effects(): void
    {
        $seller = $this->seller();
        $this->subscribe($seller, ['inbox' => ['mode' => 'preview', 'tier' => 'preview']]);
        $this->actingAs($seller)->getJson('/_package/inbox')->assertOk();
        $this->postJson('/_package/inbox')->assertForbidden()->assertJsonPath('code', 'package_feature_denied');
        $this->getJson('/_package/inbox/connect')->assertForbidden();
        SocialAccount::create(['user_id' => $seller->id, 'has_posting_permission' => true, 'has_messaging_permission' => true]);
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_missing_unknown_disabled_and_addon_permissions_do_not_grant_access(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller, ['email_marketing' => ['mode' => 'addon_required', 'tier' => 'addon'], 'inbox' => ['mode' => 'disabled', 'tier' => 'none']]);
        $this->actingAs($seller)->getJson('/email/dashboard')->assertForbidden();
        $this->getJson('/_package/inbox')->assertForbidden();
        $service = app(PackageAccessService::class);
        $this->assertFalse($service->allows($seller, 'imaginary_module'));
        $subscription->bundle->update(['access_settings' => []]);
        $this->assertFalse($service->allows($seller, 'publishing'));
    }

    public function test_expiry_inactive_and_future_subscriptions_are_denied_but_billing_and_support_work(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller, attributes: ['end_date' => '2026-10-17']);
        $this->actingAs($seller)->getJson('/_package/inbox')->assertForbidden()->assertJsonPath('code', 'subscription_required');
        $this->get('/_package/inbox')->assertRedirect(route('admin.subscription.select'));
        $this->get('/subscription/plans')->assertOk();
        $this->get('/help-center')->assertOk();
        $this->get('/support/tickets')->assertOk();
        $subscription->update(['end_date' => '2027-10-01', 'is_active' => false]);
        $this->getJson('/_package/inbox')->assertForbidden();
        $subscription->update(['is_active' => true, 'start_date' => '2026-10-19']);
        $this->getJson('/_package/inbox')->assertForbidden();
        $subscription->update(['start_date' => '2026-10-01', 'end_date' => '2026-10-18']);
        $this->getJson('/_package/inbox')->assertOk();
    }

    public function test_platform_staff_cannot_use_seller_modules_and_retired_packages_keep_existing_access(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller);
        $subscription->bundle->update(['is_active' => false]);
        $this->actingAs($seller)->getJson('/_package/inbox')->assertOk();
        $seller->is_active = false;
        $seller->save();
        $this->assertFalse(app(PackageAccessService::class)->allows($seller, 'inbox', true));
        $seller->is_active = true;
        $seller->save();
        $seller->assignRole('admin');
        $this->getJson('/_package/inbox')->assertForbidden();
    }

    public function test_yearly_subscription_has_monthly_usage_resets_and_idempotent_reservations(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller, limits: ['ai_credits' => 1]);
        $usage = app(PackageUsageService::class);
        $usage->reserve($seller, 'ai_credits', 1, 'first');
        $usage->reserve($seller, 'ai_credits', 1, 'first');
        $this->assertSame(1, $usage->used($subscription, 'ai_credits'));
        $this->assertLimit(fn () => $usage->reserve($seller, 'ai_credits', 1, 'second'));
        $other = $this->seller();
        $this->subscribe($other, limits: ['ai_credits' => 1]);
        $usage->reserve($other, 'ai_credits', 1, 'first');
        $this->travelTo(now()->setDate(2026, 11, 1));
        $this->assertSame(0, $usage->used($subscription, 'ai_credits'));
        $usage->reserve($seller, 'ai_credits', 1, 'next-month');
        $this->assertSame(1, $usage->used($subscription, 'ai_credits'));
    }

    public function test_month_end_anchor_does_not_drift_and_trial_has_no_monthly_reset(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller, attributes: ['start_date' => '2026-01-31']);
        $usage = app(PackageUsageService::class);
        $this->travelTo(now()->setDate(2026, 2, 28));
        $this->assertSame('2026-02-28', $usage->periodStart($subscription, 'monthly'));
        $this->travelTo(now()->setDate(2026, 3, 30));
        $this->assertSame('2026-02-28', $usage->periodStart($subscription, 'monthly'));
        $this->travelTo(now()->setDate(2026, 3, 31));
        $this->assertSame('2026-03-31', $usage->periodStart($subscription, 'monthly'));
        $subscription->status = 'trial';
        $this->assertSame('2026-01-31', $usage->periodStart($subscription, 'monthly'));
    }

    public function test_post_allowance_counts_destinations_once_and_is_not_refunded_by_deletion(): void
    {
        $seller = $this->seller();
        $this->subscribe($seller, limits: ['scheduled_published_posts' => 1]);
        $post = Post::create(['user_id' => $seller->id, 'status' => 'pending']);
        $post->update(['status' => 'completed']);
        $post->update(['status' => 'pending']);
        $this->assertSame(1, DB::table('package_usage')->count());
        $post->delete();
        $this->assertLimit(fn () => Post::create(['user_id' => $seller->id, 'status' => 'pending']));
        $this->assertSame(0, Post::count());
        $draft = Post::create(['user_id' => $seller->id, 'status' => 'draft']);
        $this->assertLimit(fn () => $draft->update(['status' => 'pending']));
        Post::create(['user_id' => $seller->id, 'status' => 'completed', 'post_id' => 'historical-provider-id']);
        $this->assertSame(1, DB::table('package_usage')->count());
    }

    public function test_separate_account_capacities_and_capability_changes_preserve_existing_accounts(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller, limits: ['publishing_accounts' => 1, 'advertising_accounts' => 1]);
        $first = SocialAccount::create(['user_id' => $seller->id, 'has_posting_permission' => true]);
        $ad = SocialAccount::create(['user_id' => $seller->id, 'has_ads_permission' => true]);
        $this->assertLimit(fn () => SocialAccount::create(['user_id' => $seller->id, 'has_posting_permission' => true]));
        $this->assertLimit(fn () => $ad->update(['has_posting_permission' => true]));
        $subscription->bundle->update(['entitlement_settings' => ['publishing_accounts' => 0, 'advertising_accounts' => 0]]);
        $first->update(['name' => 'Existing account retained']);
        $this->assertSame(2, SocialAccount::count());
    }

    public function test_gallery_storage_counts_soft_deleted_files_and_category_capacity(): void
    {
        $seller = $this->seller();
        $this->subscribe($seller, limits: ['media_storage_gb' => 1, 'content_categories' => 1]);
        $file = MediaAsset::create(['user_id' => $seller->id, 'size' => 1024 ** 3]);
        $file->delete();
        $this->assertLimit(fn () => MediaAsset::create(['user_id' => $seller->id, 'size' => 1]));
        PostCategory::create(['user_id' => $seller->id, 'name' => 'One']);
        $this->assertLimit(fn () => PostCategory::create(['user_id' => $seller->id, 'name' => 'Two']));
    }

    public function test_failed_operations_do_not_consume_quota_and_unlimited_is_explicit(): void
    {
        $seller = $this->seller();
        $this->subscribe($seller, limits: ['ai_credits' => 1, 'scheduled_published_posts' => -1]);
        $this->actingAs($seller)->postJson('/_package/ai-fail')->assertUnprocessable();
        $this->assertSame(0, DB::table('package_usage')->count());
        $this->postJson('/_package/ai')->assertOk();
        $this->postJson('/_package/ai')->assertUnprocessable()->assertJsonValidationErrors('package');
        for ($i = 0; $i < 3; $i++) {
            Post::create(['user_id' => $seller->id, 'status' => 'pending']);
        }
        $this->assertSame(3, Post::count());
    }

    public function test_paid_subscription_dates_are_months_not_days(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller);
        $service = app(SubscriptionService::class);
        $annual = $service->renew($subscription, 'yearly');
        $this->assertSame(12, $annual->billing_period);
        $this->assertSame('2027-10-18', $annual->end_date->toDateString());
        $monthly = $service->renew($subscription, 'monthly');
        $this->assertSame('2026-11-18', $monthly->end_date->toDateString());
    }

    public function test_usage_page_remains_available_after_expiry_and_is_scoped_to_its_owner(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller);
        app(PackageUsageService::class)->reserve($seller, 'ai_credits', 1, 'owned');
        $other = $this->seller();
        $this->subscribe($other);
        app(PackageUsageService::class)->reserve($other, 'ai_credits', 2, 'other');
        $this->actingAs($seller)->getJson('/subscription/usage?user_id='.$other->id)->assertOk()
            ->assertJsonPath('usage.ai_credits.used', 1)->assertJsonPath('active', true);
        $subscription->update(['end_date' => '2026-10-17']);
        $this->get('/subscription/usage')->assertOk()->assertSee('Your package and usage');
        $this->getJson('/subscription/usage')->assertOk()->assertJsonPath('active', false);
    }

    public function test_migration_backfills_structured_modes_without_using_display_text_as_a_permission(): void
    {
        $bundle = Bundle::create(['name_en' => 'Legacy', 'name_ar' => 'قديمة', 'entitlement_settings' => [
            'publishing_accounts' => 2, 'advertising_accounts' => 0, 'messaging_inbox_access' => 'Preview',
            'email_marketing_access' => 'Add-on or included allowance', 'ai_agent_access' => 'Unknown wording',
        ]]);
        $migration = require database_path('migrations/2026_10_18_120000_add_seller_package_access.php');
        $migration->down();
        $migration->up();
        $modes = $bundle->fresh()->access_settings;
        $this->assertSame('included', $modes['publishing']['mode']);
        $this->assertSame('disabled', $modes['advertising']['mode']);
        $this->assertSame('preview', $modes['inbox']['mode']);
        $this->assertSame('addon_required', $modes['email_marketing']['mode']);
        $this->assertSame('disabled', $modes['ai_agent']['mode']);
    }

    public function test_admin_can_save_access_modes_and_unstructured_permissions_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $access = array_fill_keys(array_keys(config('package_access.modules')), ['mode' => 'included', 'tier' => 'basic']);
        $access['inbox'] = ['mode' => 'preview', 'tier' => 'preview'];
        $data = ['name_en' => 'Managed', 'name_ar' => 'باقة', 'slug' => 'managed', 'price' => 49, 'yearly_price' => 411.6,
            'currency' => 'SAR', 'sort_order' => 1, 'is_active' => 1, 'is_popular' => 0, 'is_free' => 0, 'trial_days' => 14, 'version' => 0,
            'entitlements' => collect(config('package_features'))->map(fn ($f) => $f['default'])->all(), 'access' => $access];
        $this->actingAs($admin)->post('/plans', $data)->assertRedirect(route('plans.index'));
        $bundle = Bundle::where('slug', 'managed')->firstOrFail();
        $this->assertSame('preview', $bundle->access_settings['inbox']['mode']);
        $data['version'] = $bundle->version;
        $data['access']['inbox']['mode'] = 'Basic';
        $this->putJson('/plans/'.$bundle->id, $data)->assertUnprocessable()->assertJsonValidationErrors('access.inbox.mode');
    }

    public function test_scheduled_publish_service_denies_expired_packages_before_any_provider_calls(): void
    {
        $seller = $this->seller();
        $subscription = $this->subscribe($seller);
        $post = Post::create(['user_id' => $seller->id, 'status' => 'pending']);
        $subscription->update(['end_date' => '2026-10-17']);
        $service = (new \ReflectionClass(XPostService::class))->newInstanceWithoutConstructor();
        try {
            $service->publishPost($post);
            $this->fail('Expected package authorization before provider calls.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('pending', $post->fresh()->status);
    }

    public function test_provider_scheduled_email_is_cancelled_when_package_access_ends(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
        });
        $seller = $this->seller();
        $this->subscribe($seller, ['email_marketing' => ['mode' => 'addon_required', 'tier' => 'addon']]);
        $campaign = EmailCampaign::forceCreate(['user_id' => $seller->id, 'status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
        $service = \Mockery::mock(SendGridCampaignService::class);
        $service->shouldReceive('cancel')->once()->withArgs(fn ($item) => $item->id === $campaign->id)->andReturn(['success' => true]);
        $service->shouldNotReceive('checkStatus');
        $this->app->instance(SendGridCampaignService::class, $service);
        $this->artisan('email-marketing:send-scheduled')->assertSuccessful();
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->scheduled_at);
    }

    public function test_hashtag_allowance_cannot_be_bypassed_by_requesting_another_content_kind(): void
    {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
        DB::table('admin_settings')->insert(['key' => 'gemini_api_key_free', 'value' => 'test-key']);
        Settings::forgetRequestCache();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['title' => 'Title', 'description' => 'Caption', 'hashtags' => ['example']])]]]]],
        ])]);
        $seller = $this->seller();
        $subscription = $this->subscribe($seller, limits: ['ai_credits' => 10, 'hashtag_generation' => 1]);
        $this->actingAs($seller)->postJson('/posts/generate-ai-content', ['prompt' => 'Example', 'kind' => 'description'])
            ->assertOk()->assertJsonPath('data.description', 'Caption')->assertJsonMissingPath('data.hashtags');
        $this->postJson('/posts/generate-ai-content', ['prompt' => 'Example', 'kind' => 'hashtags'])
            ->assertOk()->assertJsonPath('data.hashtags.0', '#example');
        $usage = app(PackageUsageService::class);
        $this->assertSame(2, $usage->used($subscription, 'ai_credits'));
        $this->assertSame(1, $usage->used($subscription, 'hashtag_generation'));
        $this->postJson('/posts/generate-ai-content', ['prompt' => 'Example', 'kind' => 'all'])
            ->assertUnprocessable()->assertJsonValidationErrors('package');
        $this->assertSame(2, $usage->used($subscription, 'ai_credits'));
        Http::assertSentCount(2);
    }
}
