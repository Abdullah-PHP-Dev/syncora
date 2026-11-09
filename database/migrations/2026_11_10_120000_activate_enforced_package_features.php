<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The admin feature catalog (Plans > Features) must list the limits the app
 * actually enforces (config/package_access.php reads publishing_accounts,
 * ai_credits, team_members...). Databases that never ran ReferencePackageSeeder
 * still showed only the legacy keys (social_posts, ads_manager...), which no
 * code reads: admins could not grant publishing, ads, media, AI or team access.
 *
 * Same catalog step as ReferencePackageSeeder, without touching any plan's
 * prices, names or values. Re-runnable; legacy rows are kept for history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('package_features')) {
            return;
        }
        $definitions = config('package_reference.features', []);
        $order = 0;
        foreach ($definitions as $key => $definition) {
            $order++;
            $existing = DB::table('package_features')->where('key', $key)->first();
            if ($existing) {
                // Keep admin-edited names; only make sure it is offered.
                DB::table('package_features')->where('id', $existing->id)->update(['is_active' => true, 'updated_at' => now()]);
                continue;
            }
            DB::table('package_features')->insert([
                'key' => $key, 'name_en' => $definition['name_en'], 'name_ar' => $definition['name_ar'],
                'type' => $definition['type'], 'is_active' => true, 'version' => 1, 'sort_order' => $order,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Workspaces (added 2026-11-09) is enforced too: keep it after the reference set.
        DB::table('package_features')->where('key', 'workspaces')->update(['sort_order' => $order + 1]);

        DB::table('package_features')->whereIn('key', array_keys(config('package_features', [])))
            ->whereNotIn('key', array_keys($definitions))
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Catalog visibility only; nothing to undo safely.
    }
};
