<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Optional workspace logo (path on config('workspaces.logo_disk')). Re-runnable. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workspaces') && ! Schema::hasColumn('workspaces', 'logo_path')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->string('logo_path')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('workspaces', 'logo_path')) {
            Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('logo_path'));
        }
    }
};
