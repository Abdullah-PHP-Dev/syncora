<?php

namespace Tests\Feature\Packages;

use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\PricingController;
use App\Models\Bundle;
use App\Models\PackageChange;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PackagePresentationService;
use Database\Seeders\BundleSeeder;
use Database\Seeders\ReferencePackageSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PackageManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LocaleCookieRedirect::class,
            LaravelLocalizationRedirectFilter::class,
        ]);
        foreach (['0001_01_01_000000_create_users_table', '2026_06_13_213124_create_permission_tables', '2026_08_17_233010_create_bundles_table', '2026_10_17_120000_create_package_entitlements'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('size')->default(0); $table->timestamps();
        });
        (require database_path('migrations/2026_10_18_120000_add_seller_package_access.php'))->up();
        foreach (['admin', 'customer_support', 'seller'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function planData(array $overrides = []): array
    {
        return array_replace([
            'name_en' => 'Growth', 'name_ar' => 'النمو', 'slug' => 'growth', 'price' => '49.50',
            'yearly_price' => '499', 'currency' => 'SAR', 'sort_order' => 2,
            'is_active' => 1, 'is_free' => 0, 'is_popular' => 1, 'trial_days' => 15, 'version' => 0,
            'features_en' => "First benefit\n0\nLast benefit", 'features_ar' => 'دعم مميز',
            'entitlements' => collect(config('package_features'))->map(fn ($feature) => $feature['type'] === 'boolean' ? 1 : -1)->all(),
        ], $overrides);
    }

    private function featureData(array $overrides = []): array
    {
        return array_replace([
            'key' => 'priority_support', 'name_en' => 'Priority support', 'name_ar' => 'دعم ذو أولوية',
            'description' => 'Support tier', 'type' => 'benefit', 'sort_order' => 20, 'is_active' => 1, 'version' => 0,
        ], $overrides);
    }

    public function test_admin_can_create_feature_and_configure_each_value_type_on_a_package(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->post('/package-features', $this->featureData())->assertRedirect(route('package-features.index'));
        $data = $this->planData();
        $data['entitlements']['priority_support'] = 'Dedicated support';
        $data['entitlements']['social_posts'] = 0;
        $data['entitlements']['social_accounts'] = 5;
        $data['entitlements']['posts_per_cycle'] = 100;
        $this->post('/plans', $data)->assertRedirect(route('plans.index'));
        $plan = Bundle::firstOrFail();
        $this->assertSame(false, $plan->entitlement_settings['social_posts']);
        $this->assertSame(5, $plan->entitlement_settings['social_accounts']);
        $this->assertSame(100, $plan->entitlement_settings['posts_per_cycle']);
        $this->assertSame('Dedicated support', $plan->entitlement_settings['priority_support']);
        $this->assertSame(['First benefit', '0', 'Last benefit'], $plan->display_features);
        $this->assertDatabaseHas('package_changes', ['entity_type' => 'bundle', 'entity_id' => $plan->id, 'actor_id' => $admin->id]);
        $this->get('/plans/'.$plan->id.'/edit')->assertOk()->assertSee('Dedicated support')->assertSee('Recent changes');
    }

    public function test_staff_and_sellers_cannot_manage_features_or_package_permissions(): void
    {
        $feature = PackageFeature::firstOrFail();
        foreach (['customer_support', 'seller'] as $role) {
            $this->actingAs($this->user($role));
            foreach (['/package-features', '/package-features/create', '/package-features/'.$feature->id.'/edit', '/plans', '/plans/create'] as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->post('/package-features', $this->featureData())->assertForbidden();
            $this->put('/package-features/'.$feature->id, $this->featureData())->assertForbidden();
            $this->post('/plans', $this->planData())->assertForbidden();
        }
        $this->assertSame(0, PackageChange::count());
    }

    public function test_archiving_a_feature_preserves_existing_package_settings(): void
    {
        $this->actingAs($this->user())->post('/plans', $this->planData())->assertRedirect();
        $plan = Bundle::firstOrFail();
        $feature = PackageFeature::where('key', 'social_posts')->firstOrFail();
        $this->put('/package-features/'.$feature->id, $this->featureData([
            'key' => $feature->key, 'type' => $feature->type, 'is_active' => 0, 'version' => 1,
        ]))->assertRedirect();
        $this->get('/plans/'.$plan->id.'/edit')->assertOk()->assertDontSee('id="entitlement_social_posts"', false);
        $data = $this->planData(['version' => 1]);
        unset($data['entitlements']['social_posts']);
        $this->put('/plans/'.$plan->id, $data)->assertRedirect();
        $this->assertTrue($plan->fresh()->entitlement_settings['social_posts']);
        $this->assertFalse($feature->fresh()->is_active);
    }

    public function test_invalid_limits_and_unknown_feature_keys_are_rejected(): void
    {
        $this->actingAs($this->user());
        foreach ([-2, 1.5, 'unlimited', 1000000001] as $invalid) {
            $data = $this->planData();
            $data['entitlements']['social_accounts'] = $invalid;
            $this->postJson('/plans', $data)->assertUnprocessable()->assertJsonValidationErrors('entitlements.social_accounts');
        }
        $data = $this->planData();
        $data['entitlements']['invented_permission'] = 1;
        $this->postJson('/plans', $data)->assertUnprocessable()->assertJsonValidationErrors('entitlements');
        $data = $this->planData();
        unset($data['entitlements']['social_posts']);
        $this->postJson('/plans', $data)->assertUnprocessable()->assertJsonValidationErrors('entitlements.social_posts');
        $this->assertSame(0, Bundle::count());
    }

    public function test_feature_code_and_type_are_unique_and_immutable(): void
    {
        $this->actingAs($this->user())->post('/package-features', $this->featureData())->assertRedirect();
        $feature = PackageFeature::where('key', 'priority_support')->firstOrFail();
        $this->postJson('/package-features', $this->featureData())->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->putJson('/package-features/'.$feature->id, $this->featureData(['version' => 1, 'type' => 'quota']))->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->putJson('/package-features/'.$feature->id, $this->featureData(['version' => 1, 'key' => 'different_code']))->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->postJson('/package-features', $this->featureData(['key' => 'bad.key']))->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->assertSame(1, PackageChange::count());
    }

    public function test_stale_edits_cannot_overwrite_newer_package_or_feature_changes(): void
    {
        $this->actingAs($this->user())->post('/plans', $this->planData())->assertRedirect();
        $plan = Bundle::firstOrFail();
        $this->put('/plans/'.$plan->id, $this->planData(['version' => 1, 'price' => 79]))->assertRedirect();
        $this->putJson('/plans/'.$plan->id, $this->planData(['version' => 1, 'price' => 99]))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertEquals(79, $plan->fresh()->price);
        $this->assertSame(2, $plan->fresh()->version);
        $this->post('/package-features', $this->featureData())->assertRedirect();
        $feature = PackageFeature::where('key', 'priority_support')->firstOrFail();
        $this->put('/package-features/'.$feature->id, $this->featureData(['version' => 1, 'name_en' => 'New name']))->assertRedirect();
        $this->putJson('/package-features/'.$feature->id, $this->featureData(['version' => 1]))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame('New name', $feature->fresh()->name_en);
    }

    public function test_zero_and_unlimited_limits_are_distinct_and_free_plans_require_zero_prices(): void
    {
        $this->actingAs($this->user());
        $data = $this->planData();
        $data['entitlements']['social_accounts'] = 0;
        $this->post('/plans', $data)->assertRedirect();
        $plan = Bundle::firstOrFail();
        $this->assertSame(0, $plan->entitlement_settings['social_accounts']);
        $this->assertSame(-1, $plan->entitlement_settings['posts_per_cycle']);
        $this->post('/plans', $this->planData(['slug' => 'free', 'is_free' => 1]))->assertSessionHasErrors('price');
        $this->assertSame(1, Bundle::count());
    }

    public function test_catalog_search_and_arabic_editor_render_and_sidebar_has_no_package_dropdown(): void
    {
        $this->actingAs($this->user())->get('/package-features?search=social_accounts&type=capacity')->assertOk()->assertSee('social_accounts')->assertDontSee('<code>posts_per_cycle</code>', false);
        $this->get('/plans/create')->assertOk()->assertSee('name="entitlements[social_accounts]"', false)
            ->assertDontSee('id="ov-sidebar-plans-index"', false)->assertSee('data-module="plans.index"', false);
        app()->setLocale('ar');
        $html = view('team.features.form', ['feature' => new PackageFeature])->render();
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('إنشاء ميزة', $html);
    }

    public function test_package_can_be_saved_when_all_features_are_archived(): void
    {
        PackageFeature::query()->update(['is_active' => false]);
        $data = $this->planData();
        unset($data['entitlements']);
        $this->actingAs($this->user())->post('/plans', $data)->assertRedirect(route('plans.index'));
        $this->assertSame([], Bundle::firstOrFail()->entitlement_settings);
    }

    public function test_reference_allocation_matches_all_four_packages_and_preserves_existing_identity(): void
    {
        $this->seed(BundleSeeder::class);
        $starterId = Bundle::where('slug', 'starter')->value('id');
        $businessId = Bundle::where('slug', 'empire')->value('id');
        $pro = Bundle::where('slug', 'pro')->firstOrFail();
        $pro->meta = array_replace($pro->meta ?? [], ['yearly_price' => 999, 'custom_setting' => 'preserved']);
        $pro->save();
        (require database_path('migrations/2026_08_17_234708_create_subscriptions_table.php'))->up();
        $subscription = Subscription::create([
            'user_id' => $this->user('seller')->id, 'bundle_id' => $pro->id,
            'bundle_name' => 'Pro', 'status' => 'active', 'start_date' => '2026-10-01', 'end_date' => '2026-11-01',
        ]);
        $beforeSubscription = $subscription->fresh()->getAttributes();
        $this->seed(ReferencePackageSeeder::class);
        $keys = ['publishing_accounts', 'advertising_accounts', 'scheduled_published_posts', 'ai_credits', 'hashtag_generation', 'comments_replies', 'messaging_inbox_access', 'analytics_history_days', 'media_storage_gb', 'team_members', 'content_categories', 'post_approval', 'data_export', 'data_import', 'google_analytics', 'email_marketing_access', 'ai_agent_access', 'support_level'];
        $expected = [
            'free_trial' => [2, 1, 15, 100, 10, 'Limited', 'Preview', 7, 1, 1, 2, 'No', 'No', 'No', false, 'No', 'No', 'No'],
            'starter' => [4, 1, 50, 100, 30, 'Basic', 'Basic', 30, 5, 1, 5, 'No', 'Basic', 'No', false, 'Add-on', 'No', 'Standard'],
            'pro' => [10, 5, 300, 500, 150, 'Expanded', 'Full supported channels', 90, 25, 3, 20, 'Basic', 'Yes', 'Limited', true, 'Add-on or included allowance', 'Limited', 'Priority'],
            'empire' => [25, 15, 1000, 2000, 500, 'Expanded', 'Full supported channels', 365, 100, 10, -1, 'Multi-user workflow', 'Yes', 'Yes', true, 'Included allowance', 'Advanced', 'Priority'],
        ];
        foreach ($expected as $slug => $values) {
            $plan = Bundle::where('slug', $slug)->firstOrFail();
            foreach (array_combine($keys, $values) as $key => $value) {
                $this->assertSame($value, $plan->entitlement_settings[$key], $slug.': '.$key);
            }
            $this->assertCount(18, $plan->display_features);
            $this->assertSame($slug === 'free_trial' ? 'trial' : 'monthly', $plan->meta['allowance_period']);
        }
        $this->assertSame($starterId, Bundle::where('name_en', 'Basic')->value('id'));
        $this->assertSame($businessId, Bundle::where('name_en', 'Business')->value('id'));
        $this->assertEquals(149, $pro->fresh()->price);
        $this->assertEquals(399, Bundle::find($businessId)->price);
        $this->assertSame(999.0, $pro->fresh()->yearly_price);
        $this->assertSame('preserved', $pro->fresh()->meta['custom_setting']);
        $this->assertSame($beforeSubscription, $subscription->fresh()->getAttributes());
        $this->assertSame(14, Bundle::where('slug', 'free_trial')->firstOrFail()->meta['trial_days']);
        $this->assertSame(4, Bundle::where('is_active', true)->count());
        $this->assertSame(18, PackageFeature::where('is_active', true)->count());
        $this->assertFalse(Bundle::where('slug', 'unicorn')->firstOrFail()->is_active);
        $changes = PackageChange::count();
        $this->seed(ReferencePackageSeeder::class);
        $this->assertSame($changes, PackageChange::count(), 'Reapplying the same reference must not create redundant versions.');
        $this->actingAs($this->user())->get('/plans')->assertOk()->assertSee('Compare package features')->assertSee('Total during trial')->assertSee('1,000')->assertSee('Business');
        $data = $this->planData(['slug' => 'reference_edit', 'allowance_period' => 'monthly']);
        $data['entitlements'] = Bundle::where('slug', 'pro')->firstOrFail()->entitlement_settings;
        foreach (array_keys(config('package_features')) as $legacyKey) {
            unset($data['entitlements'][$legacyKey]);
        }
        $this->post('/plans', $data)->assertRedirect(route('plans.index'));
        $this->assertSame('monthly', Bundle::where('slug', 'reference_edit')->firstOrFail()->meta['allowance_period']);
    }

    public function test_allowance_period_rejects_unknown_values(): void
    {
        $this->actingAs($this->user())->postJson('/plans', $this->planData(['allowance_period' => 'daily']))
            ->assertUnprocessable()->assertJsonValidationErrors('allowance_period');
    }

    public function test_public_pricing_uses_live_admin_features_and_keeps_monthly_allowances_on_yearly_billing(): void
    {
        $this->seed(BundleSeeder::class);
        $this->seed(ReferencePackageSeeder::class);
        $pro = Bundle::where('slug', 'pro')->firstOrFail();
        $settings = $pro->entitlement_settings;
        $settings['publishing_accounts'] = 17;
        $settings['scheduled_published_posts'] = 777;
        $settings['support_level'] = 'Dedicated specialist';
        $pro->entitlement_settings = $settings;
        $pro->price = 159;
        $pro->save();
        PackageFeature::where('key', 'publishing_accounts')->update(['name_en' => 'Publishing profiles']);
        $response = $this->get('/pricing?billing=yearly')->assertOk()
            ->assertSee('Free Trial')->assertSee('Basic')->assertSee('Business')
            ->assertSee('14-day free trial')->assertSee('Publishing profiles')->assertSee('<strong>17</strong>', false)
            ->assertSee('777 · Per month')->assertSee('15 · Total during trial')
            ->assertSee('Dedicated specialist')->assertSee('data-monthly="159.00"', false);
        $response->assertDontSee('Social accounts for publishing: 10');
        PackageFeature::where('key', 'hashtag_generation')->update(['is_active' => false]);
        Bundle::where('slug', 'empire')->update(['is_active' => false]);
        $this->get('/pricing')->assertOk()->assertDontSee('Hashtag generation')->assertDontSee('<h2>Business</h2>', false);
        app()->setLocale('ar');
        $html = app(PricingController::class)(app(PackagePresentationService::class))->render();
        $this->assertStringContainsString('حسابات النشر الاجتماعي', $html);
        $this->assertStringContainsString('شهرياً', $html);
    }

    public function test_public_pricing_handles_empty_catalog_without_showing_archived_packages(): void
    {
        Bundle::create(['name_en' => 'Hidden package', 'name_ar' => 'مخفية', 'slug' => 'hidden', 'is_active' => false]);
        $this->get('/pricing')->assertOk()->assertSee('Our plans are being updated. Please check back soon.')->assertDontSee('Hidden package');
    }

    public function test_homepage_pricing_uses_the_shared_live_catalog_and_hides_archived_entries(): void
    {
        $this->seed(BundleSeeder::class);
        $this->seed(ReferencePackageSeeder::class);
        $basic = Bundle::where('slug', 'starter')->firstOrFail();
        $settings = $basic->entitlement_settings;
        $settings['publishing_accounts'] = 13;
        $basic->fill(['price' => 69, 'entitlement_settings' => $settings])->save();
        $response = $this->get('/')->assertOk()->assertSee('Free Trial')->assertSee('Basic')->assertSee('Business')
            ->assertSee('14-day free trial')->assertSee('69.00')->assertSee('SAR')->assertSee('<strong>13</strong>', false)->assertSee('data-home-feature="publishing_accounts"', false)
            ->assertSee('Compare all features')->assertDontSee('$19')->assertDontSee('Unlimited scheduling')->assertDontSee('>forever<', false);
        $this->assertSame(4, substr_count($response->getContent(), 'data-home-plan='));
        $this->get('/pricing')->assertOk()->assertSee('Social accounts for publishing')->assertSee('<strong>13</strong>', false);
        PackageFeature::where('key', 'publishing_accounts')->update(['is_active' => false]);
        $basic->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('data-home-plan="'.$basic->id.'"', false)->assertDontSee('Social accounts for publishing:');
        app('laravellocalization')->setLocale('ar');
        app()->setLocale('ar');
        $html = app(HomeController::class)(app(PackagePresentationService::class))->render();
        $this->assertStringContainsString('التجربة المجانية', $html);
        Bundle::query()->update(['is_active' => false]);
        app('laravellocalization')->setLocale('en');
        app()->setLocale('en');
        $empty = $this->get('/')->assertOk()->assertSee('Our plans are being updated. Please check back soon.');
        $this->assertSame(0, substr_count($empty->getContent(), 'data-home-plan='));
    }

    public function test_edit_save_stays_on_editor_and_recommending_a_package_clears_others_with_history(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->post('/plans', $this->planData())->assertRedirect(route('plans.index'));
        $first = Bundle::firstOrFail();
        $this->post('/plans', $this->planData(['slug' => 'second', 'name_en' => 'Second']))->assertRedirect();
        $second = Bundle::where('slug', 'second')->firstOrFail();
        $this->assertSame(1, Bundle::where('is_popular', true)->count());
        $this->assertFalse($first->fresh()->is_popular);
        $this->assertTrue($second->is_popular);
        $demotion = PackageChange::where('entity_type', 'bundle')->where('entity_id', $first->id)->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $demotion->actor_id);
        $this->assertTrue($demotion->before['is_popular']);
        $this->assertFalse($demotion->after['is_popular']);
        $this->put('/plans/'.$first->id, $this->planData(['version' => $first->fresh()->version, 'price' => 89]))
            ->assertRedirect(route('plans.edit', $first))->assertSessionHas('success');
        $this->assertTrue($first->fresh()->is_popular);
        $this->assertFalse($second->fresh()->is_popular);
        $this->assertSame(1, Bundle::where('is_popular', true)->count());
        // An editor opened before automatic demotion must not restore stale values.
        $this->putJson('/plans/'.$second->id, $this->planData(['slug' => 'second', 'version' => $second->version]))
            ->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->put('/plans/'.$first->id, $this->planData(['version' => $first->fresh()->version, 'is_popular' => 0]))
            ->assertRedirect(route('plans.edit', $first));
        $this->assertSame(0, Bundle::where('is_popular', true)->count());
    }

    public function test_homepage_billing_toggle_renders_saved_yearly_prices_and_matching_checkout_links(): void
    {
        $this->seed(BundleSeeder::class);
        $this->seed(ReferencePackageSeeder::class);
        $plan = Bundle::where('slug', 'pro')->firstOrFail();
        $plan->meta = array_replace($plan->meta, ['yearly_price' => 999]);
        $plan->save();
        $this->actingAs($this->user('seller'));
        $yearly = $this->get('/?billing=yearly')->assertOk()
            ->assertSee('data-billing="monthly"', false)->assertSee('data-billing="yearly" class="is-selected"', false)
            ->assertSee('data-yearly="999.00">999.00</strong>', false)
            ->assertSee('83.25 SAR / month, billed yearly')
            ->assertSee('Save up to 44%')->assertSee('Save 44% with yearly billing')
            ->assertSee('14-day free trial')->assertSee('Per month')->assertSee('Total during trial');
        $this->assertStringContainsString('href="'.e(route('admin.subscription.checkout', ['plan_id' => $plan->id, 'cycle' => 'yearly'])).'"', $yearly->getContent());
        $this->assertStringContainsString('href="'.e(route('pricing', ['billing' => 'yearly'])).'#plan-comparison"', $yearly->getContent());
        $this->get('/pricing?billing=yearly')->assertOk()->assertSee('Save up to 44%')->assertSee('Save 44% with yearly billing');
        $this->get('/?billing=monthly')->assertOk()
            ->assertSee('data-billing="monthly" class="is-selected"', false)
            ->assertSee('data-yearly="999.00">149.00</strong>', false);
    }
}
