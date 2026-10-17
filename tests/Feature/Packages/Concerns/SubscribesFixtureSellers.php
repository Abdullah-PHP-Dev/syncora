<?php

namespace Tests\Feature\Packages\Concerns;

use App\Models\Bundle;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/** Provider/module tests use subscribed sellers; access-denial tests create their own packages. */
trait SubscribesFixtureSellers
{
    protected function subscribeFixtureSellers(): void
    {
        foreach ([
            'bundles' => '2026_08_17_233010_create_bundles_table',
            'subscriptions' => '2026_08_17_234708_create_subscriptions_table',
            'package_features' => '2026_10_17_120000_create_package_entitlements',
            'package_usage' => '2026_10_18_120000_add_seller_package_access',
        ] as $table => $migration) {
            if (! Schema::hasTable($table)) {
                (require database_path('migrations/'.$migration.'.php'))->up();
            }
        }
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('is_active')->default(true));
        }
        Role::findOrCreate('seller', 'web');
        User::created(function (User $user) {
            $user->assignRole('seller');
            $bundle = Bundle::create([
                'name_en' => 'Provider test package', 'name_ar' => 'باقة الاختبار', 'slug' => 'fixture-'.$user->id,
                'access_settings' => array_fill_keys(array_keys(config('package_access.modules')), ['mode' => 'included', 'tier' => 'full']),
                'entitlement_settings' => collect(config('package_reference.features'))->map(fn ($f) => in_array($f['type'], ['capacity', 'quota'], true) ? -1 : true)->all(),
                'meta' => ['allowance_period' => 'monthly'],
            ]);
            Subscription::create(['user_id' => $user->id, 'bundle_id' => $bundle->id, 'bundle_name' => $bundle->name_en,
                'status' => 'active', 'is_active' => true, 'start_date' => today()->toDateString(), 'end_date' => today()->addYear()->toDateString()]);
        });
    }
}
