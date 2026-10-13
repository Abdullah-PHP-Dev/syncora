<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WorkspaceOverviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkspaceOverviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(12, 0));
    }

    private function createTables(): void
    {
        foreach (['posts', 'ad_campaigns', 'email_campaigns', 'post_comments', 'copilot_messages', 'media_assets', 'social_accounts', 'tickets', 'email_subscribers', 'faqs', 'user_integrations', 'conversations'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status')->default('draft');
                $table->string('platform')->default('facebook');
                $table->timestamps();
                if ($name === 'media_assets' || $name === 'faqs') $table->softDeletes();
                if ($name === 'user_integrations') $table->boolean('is_enabled')->default(true);
                if ($name === 'social_accounts') $table->boolean('is_token_valid')->default(true);
                if ($name === 'conversations') {
                    $table->unsignedBigInteger('social_account_id');
                    $table->unsignedInteger('unread_count')->default(0);
                }
                if ($name === 'posts') {
                    $table->string('title')->nullable();
                    $table->text('content')->nullable();
                    $table->timestamp('schedule_at')->nullable();
                }
                if ($name === 'email_campaigns') {
                    $table->timestamp('sent_at')->nullable();
                    foreach (['sent_count', 'delivered_count', 'opened_count', 'clicked_count'] as $column) $table->unsignedInteger($column)->default(0);
                }
            });
        }
    }

    private function insert(string $table, array $attributes = []): int
    {
        return DB::table($table)->insertGetId($attributes + ['user_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function data(int $days = 7): array
    {
        $user = new User;
        $user->id = 1;
        return app(WorkspaceOverviewService::class)->data($user, $days);
    }

    public function test_all_modules_are_scoped_to_the_seller_and_charts_are_zero_filled(): void
    {
        $this->insert('posts', ['title' => 'Mine', 'status' => 'published']);
        $this->insert('posts', ['user_id' => 2, 'title' => 'Private', 'status' => 'failed']);
        $account = $this->insert('social_accounts');
        $other = $this->insert('social_accounts', ['user_id' => 2]);
        $this->insert('conversations', ['social_account_id' => $account, 'unread_count' => 3]);
        $this->insert('conversations', ['social_account_id' => $other, 'unread_count' => 99]);
        foreach (['ad_campaigns', 'email_campaigns', 'post_comments', 'copilot_messages', 'media_assets', 'tickets', 'faqs', 'user_integrations'] as $table) {
            $this->insert($table);
            $this->insert($table, ['user_id' => 2]);
        }
        $this->insert('email_subscribers', ['status' => 'subscribed']);
        $this->insert('email_subscribers', ['user_id' => 2, 'status' => 'subscribed']);
        $data = $this->data();
        $this->assertCount(12, $data['modules']);
        foreach ($data['modules'] as $module) $this->assertSame(1, $module['total'], $module['key']);
        $this->assertSame(3, $data['unread']);
        $this->assertSame(['published' => 1], $data['statuses']);
        $this->assertSame('Mine', $data['recent']->first()->title);
        $this->assertCount(7, $data['labels']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 1], $data['series']['posts']);
    }

    public function test_period_boundaries_email_totals_and_upcoming_schedule_are_real(): void
    {
        $this->insert('posts', ['created_at' => today()->subDays(6), 'status' => 'scheduled', 'schedule_at' => now()->addDay()]);
        $this->insert('posts', ['created_at' => today()->subDays(7), 'status' => 'published']);
        $this->insert('posts', ['created_at' => today()->subDays(14)]);
        $this->insert('email_campaigns', ['sent_at' => now(), 'sent_count' => 10, 'delivered_count' => 9, 'opened_count' => 4, 'clicked_count' => 2]);
        $this->insert('email_campaigns', ['user_id' => 2, 'sent_at' => now(), 'sent_count' => 100]);
        $data = $this->data();
        $posts = collect($data['modules'])->firstWhere('key', 'posts');
        $this->assertSame(1, $posts['current']);
        $this->assertSame(1, $posts['previous']);
        $this->assertSame(3, $posts['total']);
        $this->assertCount(1, $data['scheduled']);
        $this->assertSame(10, (int) $data['emailTotals']->sent);
        $this->assertSame(2, (int) $data['emailTotals']->clicked);
    }

    public function test_empty_workspace_has_honest_zero_metrics(): void
    {
        $data = $this->data(30);
        $this->assertSame(0, array_sum(array_column($data['modules'], 'total')));
        $this->assertCount(30, $data['labels']);
        $this->assertSame([], $data['statuses']);
        $this->assertSame(0, (int) $data['emailTotals']->sent);
    }

    public function test_uninstalled_modules_are_unavailable_and_deleted_media_is_excluded(): void
    {
        $this->insert('media_assets', ['deleted_at' => now()]);
        $this->insert('media_assets');
        $this->insert('social_accounts', ['is_token_valid' => false]);
        Schema::drop('user_integrations');
        $data = $this->data();
        $modules = collect($data['modules'])->keyBy('key');
        $this->assertSame(1, $modules['media']['total']);
        $this->assertSame(0, $modules['connections']['total']);
        $this->assertFalse($modules['integrations']['available']);
        $this->assertNull($modules['integrations']['total']);
    }

    public function test_new_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard.overview', absolute: false))->assertRedirect();
    }
}
