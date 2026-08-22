<?php

namespace Tests\Feature\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every messaging service reads its endpoints through adminSetting(),
 * which queries admin_settings from its constructor - so the table has to
 * exist before the container can resolve one.
 *
 * Created directly rather than via RefreshDatabase because RefreshDatabase
 * shells out to `migrate:fresh`, which boots the console kernel, which
 * constructs every registered Artisan command - and PublishPosts'
 * constructor injects MetaPostService, whose own constructor calls
 * adminSetting() against the not-yet-migrated database. Nothing here needs
 * the rest of the schema anyway; no row is ever persisted.
 */
trait CreatesAdminSettingsTable
{
    protected function createAdminSettingsTable(): void
    {
        if (Schema::hasTable('admin_settings')) {
            return;
        }

        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }
}
