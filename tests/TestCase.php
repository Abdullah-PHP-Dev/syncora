<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Settings memoizes all settings in a static property; without
        // this, settings read in one test class leak into the next.
        \App\Support\Settings::forgetRequestCache();
    }

    /**
     * Tests build partial schemas by hand, but every seller request resolves
     * the open workspace (UseSellerWorkspace). Production always has these
     * tables once migrated; add them wherever a fixture created users.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $schema = \Illuminate\Support\Facades\Schema::getFacadeRoot();
        if ($schema->hasTable('users') && ! $schema->hasTable('business_profiles')) {
            // The default workspace is named after the business profile.
            (require database_path('migrations/2026_09_26_100002_create_business_profiles_table.php'))->up();
        }
        if ($schema->hasTable('users') && (! $schema->hasTable('workspaces') || ! $schema->hasColumn('users', 'workspace_owner_id'))) {
            $features = $schema->hasTable('package_features');
            $had = $features && \Illuminate\Support\Facades\DB::table('package_features')->where('key', 'workspaces')->exists();
            (require database_path('migrations/2026_11_09_100000_create_workspaces.php'))->up();
            if ($features && ! $had) {
                // Only the schema: these tests assert their own plan catalog.
                \Illuminate\Support\Facades\DB::table('package_features')->where('key', 'workspaces')->delete();
            }
        }
        if ($schema->hasTable('workspaces') && ! $schema->hasColumn('workspaces', 'logo_path')) {
            (require database_path('migrations/2026_11_10_140000_add_logo_to_workspaces.php'))->up();
        }
        if ($schema->hasTable('seller_team_members') && ! $schema->hasColumn('seller_team_members', 'role')) {
            (require database_path('migrations/2026_11_11_100000_add_role_to_seller_team_members.php'))->up();
        }
        (require database_path('migrations/2026_11_11_120000_add_name_to_seller_team_members.php'))->up();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
