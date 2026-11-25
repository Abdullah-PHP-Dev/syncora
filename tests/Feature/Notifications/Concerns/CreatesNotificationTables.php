<?php

namespace Tests\Feature\Notifications\Concerns;

use App\Models\SellerTeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;

/**
 * Only the tables the notification platform touches (the full migration set doesn't run on SQLite),
 * plus one workspace: an owner (Rawda), an admin (Omar), a publishing member (Sara) and a member
 * without publishing access (Lina).
 */
trait CreatesNotificationTables
{
    protected User $owner;

    protected User $admin;

    protected User $publisher;

    protected User $outsider;

    protected SellerTeamMember $publisherMember;

    protected function createNotificationTables(): void
    {
        $this->withoutVite();
        $this->withoutMiddleware([LocaleSessionRedirect::class, LocaleCookieRedirect::class, LaravelLocalizationRedirectFilter::class]);
        foreach (['0001_01_01_000000_create_users_table', '2026_06_13_213124_create_permission_tables', '2026_08_17_233010_create_bundles_table', '2026_10_18_140000_create_seller_team_members',
            '2026_11_11_100000_add_role_to_seller_team_members', '2026_11_11_120000_add_name_to_seller_team_members',
            '2026_11_23_100000_create_platform_notification_tables', '2026_11_24_100000_create_platform_email_template_tables', '2026_11_26_100000_create_email_verification_codes_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        if (! Schema::hasColumn('users', 'workspace_owner_id')) {
            Schema::table('users', fn ($t) => $t->unsignedBigInteger('workspace_owner_id')->nullable());
        }
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn ($t) => $t->boolean('is_active')->nullable()->default(true));
        }
        Schema::create('posts', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('social_account_id')->nullable();
            $t->string('group_id')->nullable();
            $t->string('platform')->nullable();
            $t->text('content')->nullable();
            $t->string('status')->nullable();
            $t->string('visibility')->nullable();
            $t->text('error_message')->nullable();
            $t->boolean('schedule_mode')->default(true);
            $t->timestamp('schedule_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->text('platform_metadata')->nullable();
            $t->string('post_id')->nullable();
            $t->timestamps();
        });
        Schema::create('wallet_transactions', function ($t) {
            $t->id();
            $t->string('transaction_no')->nullable();
            $t->unsignedBigInteger('seller_id')->nullable();
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('direction')->nullable();
            $t->string('category')->nullable();
            $t->string('status')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('payment_gateway')->nullable();
            $t->text('remarks')->nullable();
            $t->text('meta')->nullable();
            $t->timestamp('fulfilled_at')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('subscriptions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('bundle_id')->nullable();
            $t->string('bundle_name')->nullable();
            $t->unsignedInteger('billing_period')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        $this->owner = User::factory()->create(['name' => 'Rawda', 'email' => 'rawda@example.com', 'locale' => 'en']);
        \Spatie\Permission\Models\Role::findOrCreate('seller', 'web');
        $this->owner->assignRole('seller');
        $this->admin = User::factory()->create(['name' => 'Omar', 'email' => 'omar@example.com', 'locale' => 'ar']);
        $this->publisher = User::factory()->create(['name' => 'Sara', 'email' => 'sara@example.com']);
        $this->outsider = User::factory()->create(['name' => 'Lina', 'email' => 'lina@example.com']);
        SellerTeamMember::create(['owner_id' => $this->owner->id, 'user_id' => $this->admin->id, 'email' => $this->admin->email, 'role' => 'admin', 'accepted_at' => now(), 'permissions' => []]);
        $this->publisherMember = SellerTeamMember::create(['owner_id' => $this->owner->id, 'user_id' => $this->publisher->id, 'email' => $this->publisher->email, 'role' => 'member', 'accepted_at' => now(), 'permissions' => ['publishing' => 'write']]);
        SellerTeamMember::create(['owner_id' => $this->owner->id, 'user_id' => $this->outsider->id, 'email' => $this->outsider->email, 'role' => 'member', 'accepted_at' => now(), 'permissions' => ['inbox' => 'write']]);
    }
}
