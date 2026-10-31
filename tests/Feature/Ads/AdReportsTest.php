<?php

namespace Tests\Feature\Ads;

use App\Mail\AdPerformanceReport;
use App\Models\Admin\AdCampaign;
use App\Models\AdReportSchedule;
use App\Models\Bundle;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Feature\Connections\CreatesConnectionTables;
use Tests\TestCase;

/** Facebook performance reports: CSV export and scheduled emails to the signed-in user. */
class AdReportsTest extends TestCase
{
    use CreatesAdTables;
    use CreatesConnectionTables;

    private User $user;
    private SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createConnectionTables();
        $this->createSettingsTable();
        $this->createAdTables();
        (require database_path('migrations/2026_10_29_120000_create_ad_report_schedules_table.php'))->up();
        Settings::set('ads.facebook.base_url', 'https://graph.facebook.com/v22.0/act_{accountId}');
        $this->user = User::create(['name' => 'Seller', 'email' => 'owner@brand.com', 'password' => bcrypt('x')]);
        $this->actingAs($this->user);
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        $this->account = SocialAccount::create([
            'user_id' => $this->user->id, 'platform' => 'facebook', 'platform_account_id' => '111', 'name' => 'Brand Ads', 'account_type' => 'ad_account',
            'access_token' => 'token', 'expires_at' => now()->addDays(30), 'is_token_valid' => true, 'has_ads_permission' => true,
        ]);
    }

    private function fakeInsights(string $campaignName = 'Leads KSA'): void
    {
        AdCampaign::create(['user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'platform' => 'facebook', 'name' => $campaignName, 'status' => 'active', 'objective' => 'OUTCOME_LEADS', 'ad_campaign_id' => '12001']);
        Http::fake(['graph.facebook.com/v22.0/act_111/insights*' => Http::response(['data' => [
            ['campaign_id' => '12001', 'objective' => 'OUTCOME_LEADS', 'spend' => '720.20', 'impressions' => '104980', 'reach' => '52000', 'clicks' => '2050', 'actions' => [['action_type' => 'lead', 'value' => '128']]],
        ]])]);
    }

    private function schedule(array $attributes = []): AdReportSchedule
    {
        return AdReportSchedule::create(array_merge([
            'user_id' => $this->user->id, 'social_account_id' => $this->account->id, 'platform' => 'facebook', 'name' => 'Weekly performance',
            'frequency' => 'weekly', 'weekday' => 0, 'send_time' => '09:00', 'timezone' => 'Asia/Riyadh', 'period_days' => 7,
            'recipient_email' => 'owner@brand.com', 'locale' => 'en', 'status' => 'active', 'next_run_at' => now()->subMinute(),
        ], $attributes));
    }

    public function test_export_downloads_a_formula_safe_csv_of_the_period(): void
    {
        $this->fakeInsights('=HYPERLINK("http://evil")');

        $response = $this->get(route('admin.ads.campaigns.export', ['platform' => 'facebook', 'days' => 14]))->assertOk();

        $csv = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Amount spent",720.2', $csv);
        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\")\",720.2,104980,2050,1.95%,128,lead,5.63", $csv);
        $this->assertStringContainsString('attachment; filename=facebook-ads-', $response->headers->get('Content-Disposition'));
    }

    public function test_export_needs_the_data_export_feature(): void
    {
        $bundle = Bundle::where('slug', 'fixture-' . $this->user->id)->sole();
        $bundle->update(['access_settings' => array_merge($bundle->access_settings, ['data_export' => ['mode' => 'disabled']])]);
        Http::fake();

        $this->getJson(route('admin.ads.campaigns.export', ['platform' => 'facebook']))->assertForbidden();
        $this->assertFalse($this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook']))->viewData('data')['can_export']);
        Http::assertNothingSent();
    }

    public function test_a_schedule_goes_to_the_signed_in_users_email_at_the_next_slot(): void
    {
        // A Thursday 13:00 in Riyadh, after the fixture subscription starts (so the plan is active).
        $thursday = now('UTC')->next(Carbon::THURSDAY)->setTime(10, 0);
        Carbon::setTestNow($thursday);

        $report = $this->postJson(route('admin.ads.reports.store', ['platform' => 'facebook']), [
            'name' => 'Weekly performance', 'frequency' => 'weekly', 'weekday' => 0, 'send_time' => '09:00', 'timezone' => 'Asia/Riyadh', 'period_days' => 7,
            'recipient_email' => 'attacker@evil.com',
        ])->assertOk()->json('report');

        $this->assertSame('owner@brand.com', $report['recipient_email']);
        // The following Sunday 09:00 Riyadh = 06:00 UTC.
        $this->assertSame($thursday->copy()->next(Carbon::SUNDAY)->setTime(6, 0)->toIso8601String(), Carbon::parse($report['next_run_at'])->utc()->toIso8601String());
        $this->assertDatabaseHas('ad_report_schedules', ['id' => $report['id'], 'social_account_id' => $this->account->id, 'user_id' => $this->user->id]);
        $this->assertSame([$report['id']], array_column($this->get(route('admin.ads.campaigns.index', ['platform' => 'facebook']))->viewData('data')['reports'], 'id'));

        $this->postJson(route('admin.ads.reports.store', ['platform' => 'facebook']), ['name' => 'X', 'frequency' => 'weekly', 'send_time' => '09:00', 'timezone' => 'Asia/Riyadh', 'period_days' => 7])
            ->assertStatus(422)->assertJsonValidationErrors('weekday');
        Carbon::setTestNow();
    }

    public function test_another_sellers_schedule_is_out_of_reach(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@example.com', 'password' => bcrypt('x')]);
        $theirs = AdReportSchedule::create(['user_id' => $other->id, 'social_account_id' => 999, 'platform' => 'facebook', 'name' => 'Theirs', 'frequency' => 'daily', 'send_time' => '09:00', 'timezone' => 'UTC', 'period_days' => 7, 'recipient_email' => 'o@example.com', 'status' => 'active']);
        $this->actingAs($this->user);
        Mail::fake();

        $this->patchJson(route('admin.ads.reports.update', ['platform' => 'facebook', 'id' => $theirs->id]), ['status' => 'paused'])->assertNotFound();
        $this->deleteJson(route('admin.ads.reports.destroy', ['platform' => 'facebook', 'id' => $theirs->id]))->assertNotFound();
        $this->postJson(route('admin.ads.reports.send', ['platform' => 'facebook', 'id' => $theirs->id]))->assertNotFound();
        $this->assertSame('active', $theirs->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_the_scheduler_emails_due_reports_with_the_csv_and_moves_to_the_next_slot(): void
    {
        $this->fakeInsights();
        Mail::fake();
        $due = $this->schedule();
        $later = $this->schedule(['next_run_at' => now()->addDay()]);
        $paused = $this->schedule(['status' => 'paused']);

        $this->artisan('ads:send-reports')->assertSuccessful();

        Mail::assertSent(AdPerformanceReport::class, 1);
        Mail::assertSent(AdPerformanceReport::class, fn (AdPerformanceReport $m) => $m->hasTo('owner@brand.com')
            && count($m->attachments()) === 1 && str_contains($m->report['csv'], 'Leads KSA'));
        $due->refresh();
        $this->assertNotNull($due->last_sent_at);
        $this->assertTrue($due->next_run_at->isFuture());
        $this->assertNull($later->fresh()->last_sent_at);
        $this->assertNull($paused->fresh()->last_sent_at);
    }

    public function test_a_failed_report_is_recorded_and_the_schedule_moves_on(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Session expired']], 400)]);
        Mail::fake();
        $schedule = $this->schedule();

        $this->artisan('ads:send-reports')->assertSuccessful();

        Mail::assertNothingSent();
        $schedule->refresh();
        $this->assertSame('Session expired', $schedule->last_error);
        $this->assertTrue($schedule->next_run_at->isFuture());
    }

    public function test_next_run_follows_the_frequency_in_the_schedules_time_zone(): void
    {
        $from = Carbon::parse('2026-10-29 10:00:00', 'UTC'); // Thu 13:00 Riyadh
        $make = fn (array $a) => new AdReportSchedule($a + ['send_time' => '09:00', 'timezone' => 'Asia/Riyadh']);

        $this->assertSame('2026-10-30 06:00', $make(['frequency' => 'daily'])->nextRunAfter($from)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-01 06:00', $make(['frequency' => 'weekly', 'weekday' => 0])->nextRunAfter($from)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-01 06:00', $make(['frequency' => 'monthly', 'day_of_month' => 1])->nextRunAfter($from)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-28 06:00', $make(['frequency' => 'monthly', 'day_of_month' => 28])->nextRunAfter(Carbon::parse('2026-10-28 10:00:00', 'UTC'))->format('Y-m-d H:i'));
    }
}
